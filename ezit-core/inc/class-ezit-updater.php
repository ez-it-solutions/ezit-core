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

    private EzIT_Core $core;
    private EzIT_Licensing $licensing;

    /** @var array<string,array> slug => last check_update payload */
    private array $updates = [];

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

        // check() caches for 12h — a forced flag set server-side after the
        // last check would sit unseen until expiry. A dedicated scan
        // fresh-checks everything so forced releases propagate quickly on
        // any site that gets traffic (WP-Cron still needs hits — it's
        // pull, not push).
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
            if (($p['api_key'] ?? '') !== '' && hash_equals((string) $p['api_key'], $key)) {
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
            if (($product['type'] ?? 'theme') !== 'theme') {
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
            if (($product['type'] ?? 'theme') !== 'plugin') {
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
        // server authenticates check_update by api_key alone.
        $self = ! empty($product['self']);
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

        set_transient($cache_key, $info ?: 'none', self::CACHE_TTL);
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
        if (empty($info['forced']) || empty($info['update_id'])) {
            return;
        }
        $key = 'ezit_force_' . sanitize_key($info['update_id']);
        if ((int) get_option($key, 0) >= 3) {
            return;
        }
        if (wp_next_scheduled('ezit_force_update', [$slug, $type])) {
            return;
        }
        update_option($key, (int) get_option($key, 0) + 1, false);
        // Organic path waits a beat for the current request to settle;
        // push-driven scans ($now) want it due for spawn_cron() at once.
        wp_schedule_single_event(time() + ($now ? 0 : 2 * MINUTE_IN_SECONDS), 'ezit_force_update', [$slug, $type]);
    }

    /**
     * WP-Cron worker: re-check fresh (retracting the flag stops the
     * train), rebuild the transient so the upgrader resolves our
     * ezit:// package, then upgrade through WP's own machinery.
     */
    public function apply_forced(string $slug, string $type): void
    {
        $product = $this->core->product($slug);
        if (! $product) {
            return;
        }
        $info = $this->check($slug, $product, true);
        if (! $info || empty($info['forced'])
            || ! version_compare($info['version'], $product['version'] ?: '0', '>')) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Quiet skin — forced runs are headless (WP-Cron).
        $skin = new class extends WP_Upgrader_Skin {
            public function header() {}
            public function footer() {}
            public function feedback($string, ...$args) {}
        };

        if ($type === 'plugin') {
            $file = $this->plugin_file($slug, $product);
            if ($file === '') {
                return;
            }
            wp_update_plugins(); // rebuild transient → our ezit:// entry
            (new Plugin_Upgrader($skin))->upgrade($file);
        } else {
            wp_update_themes();
            $stylesheet = (string) ($product['slug'] ?: $slug);
            (new Theme_Upgrader($skin))->upgrade($stylesheet);
        }
    }
}
