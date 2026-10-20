<?php
/**
 * EzIT_Updater — feeds server `check_update` results into WordPress' own
 * update transients, and intercepts the package download so it goes
 * through the licensed /download_update/main/{id} endpoint.
 *
 * Themes get real WP "update available" badges + one-click updates,
 * gated by license state, with zero theme-side code.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Updater
{
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;
    /** Option: slug => {type, update_id, version} awaiting a forced apply. */
    private const PENDING_OPT = 'ezit_forced_pending';
    /** Option: slug => true — products pinned to a chosen version. */
    private const PIN_OPT = 'ezit_pinned';

    /** Set by versions_for() — the human reason the list came back empty. */
    private string $versions_error = '';

    private EzIT_Core $core;
    private EzIT_Licensing $licensing;

    /** @var array<string,array> slug => last check_update payload */
    private array $updates = [];

    /** True while apply_update() is rebuilding WP's update transient —
     *  the inject filters would otherwise re-arm the very release being
     *  installed and burn an attempt on the 3-per-update counter. */
    private bool $applying = false;

    public function __construct(EzIT_Core $core)
    {
        $this->core      = $core;
        $this->licensing = $core->licensing();

        add_filter('pre_set_site_transient_update_themes', [$this, 'inject_theme_updates']);
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_plugin_updates']);
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'licensed_download'], 10, 4);
        add_action('ezit_force_update', [$this, 'apply_forced'], 10, 2);
        add_action('ezit_updates_scan', [$this, 'scan']);
        add_filter('cron_schedules', [$this, 'cron_schedules']);

        // Server → site push for updates: the license server POSTs
        // /wp-json/ezit/v1/update-push (key = a product api_key) when a
        // forced release goes live — sites apply it within seconds
        // instead of waiting for the next 15-minute scan.
        add_action('rest_api_init', function (): void {
            register_rest_route('ezit/v1', '/update-push', [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_update_push'],
                'permission_callback' => [$this, 'rest_push_auth'],
            ]);
        });

        // Scheduling itself waits for init — wp_schedule_event runs
        // wp_get_schedules() which applies cron_schedules, and that
        // filter translates its display string. At plugins_loaded
        // (when this constructor fires) translations aren't loaded,
        // so scheduling here trips WP 6.7's _load_textdomain notice.
        add_action('init', [$this, 'schedule_scan'], 5);
    }

    /**
     * check() caches for 12h — a forced flag set server-side after the
     * last check would sit unseen until expiry. A dedicated scan
     * fresh-checks everything so forced releases propagate quickly on
     * any site that gets traffic (WP-Cron still needs hits — it's
     * pull, not push).
     */
    public function schedule_scan(): void
    {
        $ev = function_exists('wp_get_scheduled_event') ? wp_get_scheduled_event('ezit_updates_scan') : null;
        if ($ev && $ev->schedule === 'hourly') {
            wp_clear_scheduled_hook('ezit_updates_scan');
            $ev = null; // reschedule on the tighter cadence
        }
        if (! $ev && ! wp_next_scheduled('ezit_updates_scan')) {
            wp_schedule_event(time() + wp_rand(30, 300), 'ezit_15min', 'ezit_updates_scan');
        }
    }

    /** Register our 15-minute interval for the update scan. */
    public function cron_schedules(array $schedules): array
    {
        if (! isset($schedules['ezit_15min'])) {
            $schedules['ezit_15min'] = [
                'interval' => 15 * MINUTE_IN_SECONDS,
                'display'  => __('Every 15 minutes (EzIT update scan)', 'ezit-core'),
            ];
        }
        return $schedules;
    }

    /**
     * Fresh check of every registered product. Discovers updates (and
     * newly-forced ones) without waiting for a wp-admin update page load
     * or the 12h transient to expire. $now schedules due forced releases
     * to fire immediately — used by the update-push endpoint.
     */
    public function scan(bool $now = false): void
    {
        foreach ($this->core->products() as $slug => $product) {
            $info = $this->check($slug, $product, true);
            if ($info && version_compare($info['version'], $product['version'] ?: '0', '>')) {
                $this->maybe_force($slug, (string) ($product['type'] ?? 'theme'), $info, $now);
            }
        }
    }

    /** Push auth — same rule as license-push: a registered product api_key. */
    public function rest_push_auth(WP_REST_Request $r): bool
    {
        $key = (string) $r->get_param('key');
        if ($key === '') {
            return false;
        }
        foreach ($this->core->products() as $p) {
            $expected = (string) ($p['api_key'] ?? '');
            // Self products may ship without constants — their key is
            // resolved lazily from the free catalog listing.
            if ($expected === '' && ! empty($p['self'])) {
                $expected = (string) ($this->core->self_credentials()['api_key'] ?? '');
            }
            if ($expected !== '' && hash_equals($expected, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Update push handler — fresh check now, schedule any due forced
     * release for THIS request's spawned cron, and kick wp-cron so the
     * upgrade runs in a background request (the server's 5s push window
     * stays intact). Own throttle, independent of license-push.
     */
    public function rest_update_push(WP_REST_Request $r): WP_REST_Response
    {
        if (! get_transient('ezit_upush_recent')) {
            set_transient('ezit_upush_recent', 1, 15);
            $this->scan(true);
            spawn_cron();
        }
        return new WP_REST_Response(['ok' => true]);
    }

    // ── surface updates in WP's own UI ──────────────────────────────

    public function inject_theme_updates($transient)
    {
        if (! is_object($transient)) {
            $transient = new stdClass();
        }
        if (! isset($transient->response) || ! is_array($transient->response)) {
            $transient->response = [];
        }

        foreach ($this->core->products() as $slug => $product) {
            if (($product['type'] ?? 'theme') !== 'theme' || self::is_pinned($slug)) {
                continue;
            }
            $info = $this->check($slug, $product);
            if ($info && version_compare($info['version'], $product['version'] ?: '0', '>')) {
                // WP keys theme updates by stylesheet (dir name), which the
                // registry stores in $product['slug'] — the array KEY is the
                // sanitize_key()'d registry id and won't match wp_get_themes().
                $stylesheet = (string) ($product['slug'] ?: $slug);
                $transient->response[$stylesheet] = [
                    'theme'        => $stylesheet,
                    'new_version'  => $info['version'],
                    'url'          => admin_url('admin.php?page=ezit-core'),
                    'package'      => 'ezit://' . $slug . '/' . $info['update_id'],
                    'requires'     => '',
                    'requires_php' => '',
                ];
                $this->maybe_force($slug, 'theme', $info);
            }
        }

        return $transient;
    }

    // ── plugins get the same treatment ──────────────────────────────

    public function inject_plugin_updates($transient)
    {
        if (! is_object($transient)) {
            $transient = new stdClass();
        }
        if (! isset($transient->response) || ! is_array($transient->response)) {
            $transient->response = [];
        }

        foreach ($this->core->products() as $slug => $product) {
            if (($product['type'] ?? 'theme') !== 'plugin' || self::is_pinned($slug)) {
                continue;
            }
            $file = $this->plugin_file($slug, $product);
            if ($file === '') {
                continue;
            }
            $info = $this->check($slug, $product);
            if (! $info || ! version_compare($info['version'], $product['version'] ?: '0', '>')) {
                continue;
            }
            $transient->response[$file] = (object) [
                'slug'        => $slug,
                'plugin'      => $file,
                'new_version' => $info['version'],
                'url'         => admin_url('admin.php?page=ezit-core'),
                'package'     => 'ezit://' . $slug . '/' . $info['update_id'],
            ];
            $this->maybe_force($slug, 'plugin', $info);
        }

        return $transient;
    }

    /** Plugin file key WP indexes updates by ('dir/file.php'). */
    private function plugin_file(string $slug, array $product): string
    {
        if (! empty($product['file'])) {
            return (string) $product['file'];
        }
        return ! empty($product['self']) ? plugin_basename(EZIT_CORE_FILE) : '';
    }

    /**
     * "View details" overlay — without it WP asks wordpress.org about an
     * unknown slug and shows a broken popup for our plugins.
     */
    public function plugin_info($res, $action, $args)
    {
        if ($action !== 'plugin_information' || ! isset($args->slug)) {
            return $res;
        }
        $slug    = sanitize_key((string) $args->slug);
        $product = $this->core->product($slug);
        if (! $product || ($product['type'] ?? '') !== 'plugin') {
            return $res;
        }
        $info = $this->check($slug, $product);

        return (object) [
            'name'         => $product['name'] ?? $slug,
            'slug'         => $slug,
            'version'      => $info['version'] ?? ($product['version'] ?: ''),
            'author'       => '<a href="https://ez-it-solutions.com">Ez IT Solutions</a>',
            'homepage'     => 'https://ez-it-solutions.com',
            'requires'     => '6.0',
            'requires_php' => '8.0',
            'sections'     => [
                'changelog' => nl2br(esc_html((string) ($info['changelog'] ?? 'No changelog published.'))),
            ],
        ];
    }

    /**
     * Latest server release for a registered product, or null — this is
     * the public answer our admin tabs use instead of scraping WP's
     * update transient (which only refreshes on WP's own cadence and
     * can hold a stale 'none' for up to CACHE_TTL).
     * $fresh skips the 12h transient — the Themes tab asks live, same
     * as the Marketplace catalog.
     */
    public function update_for(string $slug, bool $fresh = false): ?array
    {
        $product = $this->core->product($slug);
        if (! $product) {
            return null;
        }
        if (self::is_pinned($slug)) {
            return null; // pinned — badges stay quiet, only the picker acts
        }
        $info = $this->check(sanitize_key($slug), $product, $fresh);
        if ($info && version_compare($info['version'], $product['version'] ?: '0', '>')) {
            // The flag surfaced on a UI path (marketplace/themes badge)
            // — arm it here too so a forced release never waits on the
            // inject filter or the cron scan to notice it.
            $this->maybe_force(sanitize_key($slug), (string) ($product['type'] ?? 'theme'), $info);
            return $info;
        }
        return null;
    }

    /**
     * /api/check_update — cached per product so the transient filter
     * doesn't hit the server on every admin page load. $fresh bypasses
     * the transient read (a fresh result still re-stamps it).
     */
    private function check(string $slug, array $product, bool $fresh = false): ?array
    {
        if (! $fresh && isset($this->updates[$slug])) {
            return $this->updates[$slug];
        }

        $lic = $this->licensing->license($slug);
        // 'self' products (EzIT Core itself) hold no license code — the
        // server authenticates check_update by api_key alone. Sites that
        // never had the constants defined resolve them on demand from
        // the free catalog listing.
        $self = ! empty($product['self']);
        if ($self && ($product['product_id'] === '' || ($product['api_key'] ?? '') === '')) {
            $product = array_merge($product, array_filter($this->core->self_credentials()));
        }
        if ($product['product_id'] === '' || (! $self && (! $lic || empty($lic['license_file'])))) {
            return $this->updates[$slug] = null;
        }

        $cache_key = 'ezit_update_' . $slug;
        if (! $fresh) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $this->updates[$slug] = ($cached === 'none') ? null : $cached;
            }
        }

        $res = EzIT_API::post('check_update', $product, [
            'product_id'      => $product['product_id'],
            'current_version' => $product['version'] ?: '0',
        ]);

        if (! is_wp_error($res) && class_exists('EzIT_Config')) {
            EzIT_Config::maybe_sync((int) ($res['config_version'] ?? 0));
        }

        $info = (! is_wp_error($res) && ! empty($res['status']) && ! empty($res['update_id']))
            ? $res
            : null;

        // A positive result can ride the 12h cache, but "no update"
        // mustn't blind the site half a day — publish timing is
        // unpredictable, so a miss only suppresses calls for 15 min
        // (same cadence as the forced sweep / cron scan).
        set_transient($cache_key, $info ?: 'none',
            $info ? self::CACHE_TTL : 15 * MINUTE_IN_SECONDS);
        return $this->updates[$slug] = $info;
    }

    // ── licensed package download ───────────────────────────────────

    /**
     * WP calls us before fetching `package`. Our package URLs are
     * `ezit://{slug}/{update_id}` — stream through the server instead.
     */
    public function licensed_download($reply, string $package, $upgrader, $hook_extra = [])
    {
        if (! str_starts_with($package, 'ezit://')) {
            return $reply; // not ours — core WP handles it
        }

        $parts = explode('/', substr($package, 7));
        [$slug, $update_id] = [$parts[0] ?? '', $parts[1] ?? ''];

        $product = $this->core->product($slug);
        $lic     = $this->licensing->license($slug);
        $self    = $product && ! empty($product['self']);
        if ($self && (($product['api_key'] ?? '') === '' || ($product['product_id'] ?? '') === '')) {
            $product = array_merge($product, array_filter($this->core->self_credentials()));
        }

        // Self products ship an empty license_file — the server only
        // permits that when the product's catalog listing is free +
        // published, so there's still an entitlement gate.
        if (! $product || (! $self && (! $lic || empty($lic['license_file'])))) {
            return new WP_Error('ezit_unlicensed', 'This product is not licensed.');
        }

        $file = EzIT_API::download('download_update/main/' . rawurlencode($update_id), $product, [
            'product_id'   => $product['product_id'],
            'license_file' => $lic['license_file'] ?? '',
        ]);

        return $file; // path or WP_Error — WP streams it into the upgrader
    }

    // ── forced releases ─────────────────────────────────────────────

    /**
     * check_update flagged this release `forced` → arm a one-off WP-Cron
     * event that applies it silently. Capped at 3 attempts per update_id
     * so a persistently failing upgrade stops hammering the site.
     */
    private function maybe_force(string $slug, string $type, array $info, bool $now = false): void
    {
        if (empty($info['forced']) || empty($info['update_id'])
            || $this->applying || self::forced_ignored($slug)
            || self::is_pinned($slug)) {
            return;
        }
        $key = 'ezit_force_' . sanitize_key($info['update_id']);
        if ((int) get_option($key, 0) >= 3) {
            return;
        }
        // Record outside cron too — WP-Cron only runs on hits, so a
        // quiet site could sit on a forced release forever. The pending
        // registry is what the admin-page intercept (pending_forced)
        // acts on: the first Ez IT page an admin opens applies it.
        $pending = (array) get_option(self::PENDING_OPT, []);
        $pending[$slug] = [
            'type'      => $type,
            'update_id' => (string) $info['update_id'],
            'version'   => (string) ($info['version'] ?? ''),
        ];
        update_option(self::PENDING_OPT, $pending, false);

        if (wp_next_scheduled('ezit_force_update', [$slug, $type])) {
            return;
        }
        update_option($key, (int) get_option($key, 0) + 1, false);
        // Organic path waits a beat for the current request to settle;
        // push-driven scans ($now) want it due for spawn_cron() at once.
        wp_schedule_single_event(time() + ($now ? 0 : 2 * MINUTE_IN_SECONDS), 'ezit_force_update', [$slug, $type]);
    }

    /** Slugs this site refuses to force-update — Settings → Updates. */
    public static function ignore_list(): array
    {
        return array_values(array_filter(array_map('strval',
            (array) get_option('ezit_ignore_forced', []))));
    }

    /** 'all' in the ignore list opts the whole site out. */
    public static function forced_ignored(string $slug): bool
    {
        $ig = self::ignore_list();
        return in_array('all', $ig, true) || in_array($slug, $ig, true);
    }

    // ── version pins ────────────────────────────────────────────────

    /**
     * Products pinned to a manually-chosen version — auto paths (forced
     * releases, update badges, the WP transient) all skip them; only an
     * explicit pick in the Marketplace moves the files. The option
     * survives plugin-file swaps; EZIT_PIN_PRODUCTS ('slug,slug' or
     * array) in wp-config.php pins products before the new build even
     * runs — the debug-install escape hatch.
     */
    public static function pin_list(): array
    {
        $pinned = array_keys(array_filter(
            (array) get_option(self::PIN_OPT, [])));
        if (defined('EZIT_PIN_PRODUCTS')) {
            $extra = is_array(EZIT_PIN_PRODUCTS)
                ? EZIT_PIN_PRODUCTS
                : explode(',', (string) EZIT_PIN_PRODUCTS);
            $pinned = array_merge($pinned, array_map('trim', $extra));
        }
        return array_values(array_unique(array_filter(array_map('strval', $pinned))));
    }

    /** Is this product held at its current version? */
    public static function is_pinned(string $slug): bool
    {
        return in_array($slug, self::pin_list(), true);
    }

    /** Pin/unpin a product (UI path — the constant always wins). */
    public static function set_pinned(string $slug, bool $pin): void
    {
        $pinned = (array) get_option(self::PIN_OPT, []);
        if ($pin) {
            $pinned[$slug] = true;
        } else {
            unset($pinned[$slug]);
        }
        update_option(self::PIN_OPT, $pinned, false);
    }

    /**
     * A forced release waiting to be applied, or null. Called when an
     * admin opens any Ez IT page — the reliable trigger, since WP-Cron
     * needs front-end traffic. A 15-minute sweep fresh-checks the whole
     * registry so a just-published forced release is caught even before
     * it lands in the pending option; armed entries re-verify fresh so
     * a retracted flag clears instead of trapping the admin.
     */
    public function pending_forced(): ?array
    {
        $pending = (array) get_option(self::PENDING_OPT, []);

        if (! get_transient('ezit_forced_sweep')) {
            set_transient('ezit_forced_sweep', 1, 15 * MINUTE_IN_SECONDS);
            $this->scan(); // arms + records anything newly forced
            $pending = (array) get_option(self::PENDING_OPT, []);
        }

        $changed = false;
        foreach ($pending as $slug => $p) {
            $slug    = (string) $slug;
            $product = $this->core->product($slug);
            // Gone, ignored, pinned, or burned its three attempts — drop it.
            if (! $product || self::forced_ignored($slug) || self::is_pinned($slug)
                || (int) get_option('ezit_force_' . sanitize_key((string) ($p['update_id'] ?? '')), 0) >= 3) {
                unset($pending[$slug]);
                $changed = true;
                continue;
            }
            $info = $this->check($slug, $product, true);
            if ($info && ! empty($info['forced']) && ! empty($info['update_id'])
                && version_compare((string) $info['version'], $product['version'] ?: '0', '>')) {
                if ($changed) {
                    update_option(self::PENDING_OPT, $pending, false);
                }
                return [
                    'slug'      => $slug,
                    'type'      => (string) ($product['type'] ?? 'theme'),
                    'name'      => (string) ($product['name'] ?? $slug),
                    'version'   => (string) $info['version'],
                    'update_id' => (string) $info['update_id'],
                ];
            }
            // Retracted or already applied — clear rather than trap.
            unset($pending[$slug]);
            $changed = true;
        }
        if ($changed) {
            update_option(self::PENDING_OPT, $pending, false);
        }
        return null;
    }

    /** Drop a pending entry — success, retraction or opt-out. */
    private function clear_pending(string $slug): void
    {
        $pending = (array) get_option(self::PENDING_OPT, []);
        if (isset($pending[$slug])) {
            unset($pending[$slug]);
            update_option(self::PENDING_OPT, $pending, false);
        }
    }

    /**
     * WP-Cron worker — quiet skin, result unused. The admin "Updating…"
     * screen drives the same machinery through apply_update() directly.
     */
    public function apply_forced(string $slug, string $type): void
    {
        $this->apply_update($slug, $type);
    }

    /**
     * Run one forced release through WP's own upgrader. Re-checks fresh
     * (retracting the flag stops the train), rebuilds the update
     * transient so the ezit:// package resolves, and returns true or
     * WP_Error. $skin decides where progress goes — headless for cron,
     * a collecting skin for the admin progress screen.
     *
     * @return true|WP_Error
     */
    public function apply_update(string $slug, string $type, ?WP_Upgrader_Skin $skin = null)
    {
        $product = $this->core->product($slug);
        if (! $product) {
            return new WP_Error('ezit_no_product', 'Unknown product.');
        }
        if (self::forced_ignored($slug) || self::is_pinned($slug)) {
            $this->clear_pending($slug);
            return new WP_Error('ezit_optout',
                'This product is exempt from forced updates on this site.');
        }
        $info = $this->check($slug, $product, true);
        if (! $info || empty($info['forced']) || empty($info['update_id'])
            || ! version_compare((string) $info['version'], $product['version'] ?: '0', '>')) {
            $this->clear_pending($slug); // retracted or already applied
            return new WP_Error('ezit_not_forced', 'No forced update is pending.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Quiet skin when none supplied — cron runs are headless.
        $skin = $skin ?: new class extends WP_Upgrader_Skin {
            public function header() {}
            public function footer() {}
            public function feedback($string, ...$args) {}
        };

        $this->applying = true;
        // Prime the update transient ourselves — wp_update_plugins()/
        // wp_update_themes() can bail early on a fresh last_checked
        // without firing our inject filter, and then the upgrader sees
        // no response entry and reports 'up_to_date'. Writing the entry
        // directly works on every WP version and skips a wp.org call.
        $key = $this->prime_update($slug, $type, $product, $info);
        if ($key === null) {
            $this->applying = false;
            return new WP_Error('ezit_no_file', 'Could not locate the install target.');
        }
        $result = $type === 'plugin'
            ? (new Plugin_Upgrader($skin))->upgrade($key)
            : (new Theme_Upgrader($skin))->upgrade($key);
        $this->applying = false;

        if ($result === true || is_array($result)) {
            $this->clear_pending($slug);
            return true;
        }
        return $result instanceof WP_Error ? $result
            : new WP_Error('ezit_upgrade_failed', 'The update did not complete.');
    }

    /**
     * Write our ezit:// response entry into WP's update transient and
     * return the key the upgrader expects ($file for plugins,
     * $stylesheet for themes), or null when there's no install target.
     */
    private function prime_update(string $slug, string $type, array $product, array $info): ?string
    {
        if ($type === 'plugin') {
            $file = $this->plugin_file($slug, $product);
            if ($file === '') {
                return null;
            }
            $tr = get_site_transient('update_plugins');
            if (! is_object($tr)) {
                $tr = new stdClass();
            }
            if (! isset($tr->response) || ! is_array($tr->response)) {
                $tr->response = [];
            }
            $tr->response[$file] = (object) [
                'slug'        => $slug,
                'plugin'      => $file,
                'new_version' => $info['version'],
                'url'         => admin_url('admin.php?page=ezit-core'),
                'package'     => 'ezit://' . $slug . '/' . $info['update_id'],
            ];
            set_site_transient('update_plugins', $tr);
            return $file;
        }

        $stylesheet = (string) ($product['slug'] ?: $slug);
        $tr = get_site_transient('update_themes');
        if (! is_object($tr)) {
            $tr = new stdClass();
        }
        if (! isset($tr->response) || ! is_array($tr->response)) {
            $tr->response = [];
        }
        $tr->response[$stylesheet] = [
            'theme'        => $stylesheet,
            'new_version'  => $info['version'],
            'url'          => admin_url('admin.php?page=ezit-core'),
            'package'      => 'ezit://' . $slug . '/' . $info['update_id'],
            'requires'     => '',
            'requires_php' => '',
        ];
        set_site_transient('update_themes', $tr);
        return $stylesheet;
    }

    // ── version history + pin-to-version installs ───────────────────

    /**
     * Published release history for the Marketplace version picker —
     * newest first. Cached 15 min per product (same cadence as the
     * forced sweep); Marketplace Refresh busts it.
     *
     * @return array<int,array{update_id:string,version:string,release_date:string,summary:?string,forced:bool}>
     */
    public function versions_for(string $slug, bool $fresh = false): array
    {
        $slug    = sanitize_key($slug);
        $product = $this->core->product($slug);
        $this->versions_error = '';
        if (! $product) {
            $this->versions_error = 'Product not registered on this site.';
            return [];
        }

        $self = ! empty($product['self']);
        if ($self && ($product['product_id'] === '' || ($product['api_key'] ?? '') === '')) {
            $product = array_merge($product, array_filter($this->core->self_credentials()));
        }
        if ($product['product_id'] === '') {
            $this->versions_error = 'No product_id — check_update can\'t identify this product to the server.';
            return [];
        }

        // The cached shape is [list, err] — an empty list with a stored
        // reason reads very differently than "zero releases published".
        $cache = 'ezit_versions_' . $slug;
        if (! $fresh) {
            $cached = get_transient($cache);
            if (is_array($cached) && isset($cached['list'])) {
                $this->versions_error = (string) ($cached['err'] ?? '');
                return (array) $cached['list'];
            }
        }

        $res = EzIT_API::post('versions', $product, [
            'product_id' => $product['product_id'],
        ]);
        if (is_wp_error($res)) {
            $this->versions_error = $res->get_error_message();
            $versions = [];
        } elseif (empty($res['status'])) {
            $this->versions_error = (string) ($res['message'] ?? 'The server refused the request.');
            $versions = [];
        } else {
            $versions = array_values((array) ($res['versions'] ?? []));
        }

        set_transient($cache, ['list' => $versions, 'err' => $this->versions_error], 15 * MINUTE_IN_SECONDS);
        return $versions;
    }

    /** Why the last versions_for() came back empty ('' = genuinely none). */
    public function versions_error(): string
    {
        return $this->versions_error;
    }

    /**
     * Install a specific published release — upgrade or downgrade.
     * Downloads the package by update_id under the same license rules
     * as a normal update, then runs WP's overwrite-install (the exact
     * flow the Marketplace "Install" button uses). Callers decide on
     * pinning — this just puts the chosen files in place.
     *
     * @return true|WP_Error
     */
    public function install_version(string $slug, string $update_id)
    {
        $slug    = sanitize_key($slug);
        $product = $this->core->product($slug);
        if (! $product) {
            return new WP_Error('ezit_no_product', 'Unknown product.');
        }

        // The picker only offers IDs the server listed — refuse anything
        // else so a tampered form can't fetch an arbitrary package.
        $version = '';
        foreach ($this->versions_for($slug, true) as $row) {
            if ((string) ($row['update_id'] ?? '') === $update_id) {
                $version = (string) ($row['version'] ?? '');
                break;
            }
        }
        if ($version === '') {
            return new WP_Error('ezit_bad_release', 'That release is not published for this product.');
        }

        $lic  = $this->licensing->license($slug);
        $self = ! empty($product['self']);
        if ($self && (($product['api_key'] ?? '') === '' || ($product['product_id'] ?? '') === '')) {
            $product = array_merge($product, array_filter($this->core->self_credentials()));
        }
        if (! $self && (! $lic || empty($lic['license_file']))) {
            return new WP_Error('ezit_unlicensed', 'This product is not licensed.');
        }

        $tmp = EzIT_API::download('download_update/main/' . rawurlencode($update_id), $product, [
            'product_id'   => $product['product_id'],
            'license_file' => $lic['license_file'] ?? '',
        ]);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $type     = (string) ($product['type'] ?? 'theme');
        $upgrader = $type === 'plugin' ? new Plugin_Upgrader() : new Theme_Upgrader();
        ob_start();
        $ok = $upgrader->install($tmp, [
            'overwrite_package' => true,
            'clear_destination' => true,
        ]);
        ob_end_clean();
        @unlink($tmp);

        if ($ok !== true) {
            return is_wp_error($ok) ? $ok
                : new WP_Error('ezit_install_failed', 'The package could not be installed.');
        }

        // A plugin install can deactivate the target — put it back.
        if ($type === 'plugin' && method_exists($upgrader, 'plugin_info')) {
            $file = $upgrader->plugin_info();
            if ($file && ! is_plugin_active($file)) {
                activate_plugin($file);
            }
        }

        // Whatever this site thought it knew about "latest" is stale.
        delete_transient('ezit_update_' . $slug);
        delete_transient('ezit_versions_' . $slug);
        $this->clear_pending($slug);
        $this->updates[$slug] = null;

        return true;
    }
}
