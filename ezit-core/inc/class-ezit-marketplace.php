<?php
/**
 * EzIT_Marketplace — the storefront.
 *
 * Phase 3: renders the products registered on THIS site plus the remote
 * catalog (`GET /api/catalog` — hourly cache, fails soft to empty).
 * Free listings install in-place: the catalog detail response carries
 * the product's api_key, check_update resolves the latest package, and
 * the zip streams through the standard download_update endpoint — the
 * server waves the license gate for published free items.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Marketplace
{
    private EzIT_Core $core;

    /** True when the last catalog() call failed and we're serving the
     *  cached copy — the UI uses it to flag stale data. */
    private bool $stale = false;

    /** Seconds a cached catalog counts as fresh. The transient itself
     *  lives a week as the outage fallback — this window is only about
     *  when we bother the server again. */
    private const FRESH_TTL = 300;

    public function __construct(EzIT_Core $core)
    {
        $this->core = $core;
    }

    /** Unix time the catalog last fetched successfully (0 = never). */
    public function catalog_fetched_at(): int
    {
        return (int) get_option('ezit_catalog_at', 0);
    }

    /** True when the last catalog() call failed and we're serving cache. */
    public function is_stale(): bool
    {
        return $this->stale;
    }

    /**
     * Honor ?ezit_refresh=1 — busts every marketplace-flavored cache and
     * returns whether a fresh catalog pull is required. Idempotent: the
     * tab shell calls it for the header stamp, render() calls it again.
     */
    public function handle_refresh(): bool
    {
        $fresh = isset($_GET['ezit_refresh']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended.NonceVerification — verified below
            && wp_verify_nonce(sanitize_key($_GET['_wpnonce'] ?? ''), 'ezit_refresh'); // phpcs:ignore
        if (! $fresh) {
            return false;
        }
        // Refresh means "look again" — bust the update-check cache
        // and the forced sweep too, or a stale 'none' keeps a just
        // published release invisible after the catalog reloads.
        foreach ($this->core->products() as $slug => $p) {
            delete_transient('ezit_update_' . $slug);
            delete_transient('ezit_versions_' . $slug);
        }
        delete_transient('ezit_forced_sweep');
        return true;
    }

    /** Nonce'd URL the header Refresh button points at. */
    public function refresh_url(): string
    {
        return wp_nonce_url(admin_url('admin.php?page=ezit-core&tab=marketplace&ezit_refresh=1'), 'ezit_refresh');
    }

    /**
     * Remote catalog — array of items or [] (never WP_Error so the UI can
     * render uniformly). $fresh bypasses the cache; the transient doubles
     * as a week-long stale fallback when the server is unreachable, and
     * ezit_catalog_at stamps the last successful fetch — the FRESH_TTL
     * window decides when we hit the network again.
     */
    public function catalog(bool $fresh = false): array
    {
        if (! $fresh) {
            $cached = get_transient('ezit_catalog');
            // The transient doubles as a week-long outage fallback, so
            // the fetch timestamp — not the transient — decides whether
            // we can skip the network call.
            if (is_array($cached)
                && (time() - (int) get_option('ezit_catalog_at', 0)) < self::FRESH_TTL) {
                return $cached;
            }
        }

        $res = wp_remote_get($this->core->api_url() . '/api/catalog', [
            'timeout' => 12,
            'headers' => [
                'LB-URL'  => home_url(),
                'LB-LANG' => get_locale() ?: 'en',
            ],
        ]);

        if (! is_wp_error($res) && wp_remote_retrieve_response_code($res) < 400) {
            $json  = json_decode(wp_remote_retrieve_body($res), true);
            $items = is_array($json['items'] ?? null) ? $json['items'] : [];
            set_transient('ezit_catalog', $items, WEEK_IN_SECONDS);
            update_option('ezit_catalog_at', time(), false);
            $this->stale = false;
            return $items;
        }

        // Fetch failed — serve the last good copy rather than an empty grid.
        $this->stale = true;
        $stale = get_transient('ezit_catalog');
        return is_array($stale) ? $stale : [];
    }

    /**
     * Catalog detail — `GET /api/catalog/{slug}`. Free listings include
     * `api_key`, which is what the install flow needs to authenticate
     * check_update + download_update before any license exists.
     *
     * @return array|WP_Error
     */
    public function detail(string $slug)
    {
        $slug = sanitize_key($slug);
        $cache_key = 'ezit_cat_' . $slug;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached === 'none' ? new WP_Error('ezit_not_found', 'Catalog item not found.') : $cached;
        }

        $res = wp_remote_get($this->core->api_url() . '/api/catalog/' . $slug, [
            'timeout' => 12,
            'headers' => [
                'LB-URL'  => home_url(),
                'LB-LANG' => get_locale() ?: 'en',
            ],
        ]);

        $item = null;
        if (! is_wp_error($res) && wp_remote_retrieve_response_code($res) < 400) {
            $json = json_decode(wp_remote_retrieve_body($res), true);
            $item = is_array($json['item'] ?? null) ? $json['item'] : null;
        }

        if (! $item) {
            set_transient($cache_key, 'none', 5 * MINUTE_IN_SECONDS);
            return new WP_Error('ezit_not_found', 'Catalog item not found.');
        }
        set_transient($cache_key, $item, 5 * MINUTE_IN_SECONDS);
        return $item;
    }

    /**
     * Install OR update a catalog item. Fresh installs are limited to
     * free listings (paid purchases arrive with Phase 5); items already
     * registered on this site take the licensed update path — their own
     * api_key + license_file — so paid products can update too.
     * Returns [status, message].
     *
     * Accepts the catalog slug, or a bare product_id (the Themes tab
     * knows products, not catalog slugs) which we resolve via catalog().
     *
     * Flow: detail (api_key) → check_update (latest package id) →
     * download_update zip → WP_Upgrader install w/ clear_destination →
     * plugins auto-activate, themes wait for the Themes tab switch.
     */
    public function install(string $slug, string $product_id = ''): array
    {
        $slug       = sanitize_key($slug);
        $product_id = sanitize_text_field($product_id);

        // Resolve the catalog item.
        $item = null;
        if ($slug !== '') {
            $r = $this->detail($slug);
            if (! is_wp_error($r)) {
                $item = $r;
            }
        }
        if ($item === null && $product_id !== '') {
            foreach ($this->catalog() as $c) {
                if (($c['product_id'] ?? '') === $product_id) {
                    $r = $this->detail((string) ($c['slug'] ?? ''));
                    if (! is_wp_error($r)) {
                        $item = $r;
                    }
                    break;
                }
            }
        }
        if ($item === null) {
            return ['status' => false, 'message' => __('Catalog item not found.', 'ezit-core')];
        }

        // Capability per declared type — same gates wp-admin enforces.
        $type = sanitize_key($item['type'] ?? 'product');
        $cap  = $type === 'theme' ? 'install_themes'
              : ($type === 'plugin' ? 'install_plugins' : 'manage_options');
        if (! current_user_can($cap)) {
            return ['status' => false, 'message' => __('You cannot install this product type.', 'ezit-core')];
        }

        // Already registered on this site? Then this is an update —
        // authenticate as the installed product and let its license cert
        // satisfy paid items; fresh installs keep the free-item gate.
        $pid        = (string) ($item['product_id'] ?? '');
        $local_slug = null;
        $local      = null;
        if ($pid !== '') {
            foreach ($this->core->products() as $ps => $p) {
                if (($p['product_id'] ?? '') === $pid) {
                    $local_slug = $ps;
                    $local      = $p;
                    break;
                }
            }
        }

        $lic = [];
        // Installing the newest release counts as resuming — the pin
        // only protects deliberate downgrades.
        $was_pinned = $local_slug !== null && EzIT_Updater::is_pinned($local_slug);
        if ($local !== null) {
            $creds      = $local;
            $lic        = $this->core->licensing()->license($local_slug) ?: [];
            $check_body = [
                'product_id'      => (string) $local['product_id'],
                'current_version' => (string) ($local['version'] ?? '0'),
            ];
        } else {
            if (empty($item['free']) || empty($item['api_key'])) {
                return ['status' => false, 'message' => __('Paid items install after checkout — the purchase flow ships with Phase 5.', 'ezit-core')];
            }
            // The detail response IS the trust anchor: api_key + product_id
            // let us authenticate update calls before the product self-registers.
            $creds      = ['api_key' => (string) $item['api_key']];
            $check_body = ['product_id' => $pid, 'current_version' => '0'];
        }

        $upd = EzIT_API::post('check_update', $creds, $check_body);
        if (is_wp_error($upd)) {
            return ['status' => false, 'message' => $upd->get_error_message()];
        }
        if (empty($upd['update_id'])) {
            return ['status' => false, 'message' => __('No package published for this item yet.', 'ezit-core')];
        }

        // Download — licensed products send their cert, free listings
        // authenticate with api_key alone.
        $dl_body = ['product_id' => $pid];
        if (! empty($lic['license_file'])) {
            $dl_body['license_file'] = $lic['license_file'];
        }
        $tmp = EzIT_API::download('download_update/main/' . rawurlencode((string) $upd['update_id']), $creds, $dl_body);
        if (is_wp_error($tmp)) {
            return ['status' => false, 'message' => $tmp->get_error_message()];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

        $skin     = class_exists('Automatic_Upgrader_Skin')
            ? new Automatic_Upgrader_Skin() : new WP_Upgrader_Skin();
        $upgrader = $type === 'plugin' ? new Plugin_Upgrader($skin) : new Theme_Upgrader($skin);
        ob_start();
        $ok = $upgrader->install($tmp, [
            'overwrite_package' => true,
            'clear_destination' => true,
        ]);
        ob_end_clean();
        @unlink($tmp);
        if ($ok !== true) {
            $detail = is_wp_error($ok) ? $ok->get_error_message()
                : implode(' ', array_filter((array) $skin->get_upgrade_messages()));
            return ['status' => false, 'message' => __('Package install failed.', 'ezit-core')
                . ($detail !== '' ? ' ' . $detail : '')];
        }

        delete_transient('ezit_catalog'); // the "installed" flag is stale now
        if ($local_slug !== null) {
            delete_transient('ezit_update_' . $local_slug); // …and so is the update check
        }

        $name = (string) ($item['name'] ?? $slug);

        // A plugin dir already on disk means this "Install" was an update
        // in disguise — EzIT Core reinstalls itself through this path when
        // its self-product constants aren't configured ($local never
        // matches). Self products never need a license either.
        $is_self = $slug === 'ezit-core'
            || ($pid !== '' && defined('EZIT_CORE_PRODUCT_ID') && $pid === EZIT_CORE_PRODUCT_ID);
        $updated = $local !== null
            || ($type === 'plugin' && $slug !== '' && is_dir(WP_PLUGIN_DIR . '/' . $slug));

        EzIT_Updater::record_applied($local_slug ?? $slug, $name,
            (string) ($upd['version'] ?? ''), (string) ($local['version'] ?? ''), 'manual');

        if ($type === 'plugin' && method_exists($upgrader, 'plugin_info')) {
            $file = $upgrader->plugin_info();
            if ($file && ! is_plugin_active($file)) {
                activate_plugin($file); // runs the product's own bootstrap → it self-registers
            }
            return ['status' => true, 'message' =>
                $updated
                /* translators: %s: product name */
                ? sprintf(__('%s updated — the new version is live.', 'ezit-core'), $name)
                : ($is_self
                    /* translators: %s: product name */
                    ? sprintf(__('%s installed and activated.', 'ezit-core'), $name)
                    /* translators: %s: product name */
                    : sprintf(__('%s installed and activated — it self-registers now, license it under the Licenses tab.', 'ezit-core'), $name))];
        }

        return ['status' => true, 'message' => $updated
            /* translators: %s: product name */
            ? sprintf(__('%s updated — the new version is live.', 'ezit-core'), $name)
            /* translators: %s: product name */
            : sprintf(__('%s installed — switch to it (or license it first) under the Themes tab.', 'ezit-core'), $name)];
    }

    /** Render the marketplace grid (called by EzIT_Admin's page callback). */
    public function render(): void
    {
        $products = $this->core->products();
        // Only the Refresh button forces a live pull — otherwise the
        // 5-minute freshness window applies. The tab shell may have
        // already handled the refresh for its header stamp; running it
        // again is harmless (deleting the same transients twice).
        $catalog  = $this->catalog($this->handle_refresh());
        $licensing = $this->core->licensing();
        ?>
        <div class="ezit-grid">
            <h2><?php esc_html_e('Installed on this site', 'ezit-core'); ?></h2>
            <?php if (! $products) : ?>
                <p><?php esc_html_e('No Ez IT products are registered yet. Activate a theme that supports Ez IT Core.', 'ezit-core'); ?></p>
            <?php else : ?>
                <div class="ezit-cards">
                    <?php foreach ($products as $slug => $p) : ?>
                        <div class="ezit-card">
                            <strong><?php echo esc_html($p['name']); ?></strong>
                            <span class="ezit-meta">
                                <?php echo esc_html(($p['type'] ?? 'theme') . ' · v' . ($p['version'] ?: '—')); ?>
                            </span>
                            <span class="ezit-badge <?php echo ! empty($p['self']) || $licensing->is_active($slug) ? 'is-active' : 'is-inactive'; ?>">
                                <?php echo ! empty($p['self'])
                                    ? esc_html__('Core', 'ezit-core')
                                    : ($licensing->is_active($slug)
                                        ? esc_html__('Licensed', 'ezit-core')
                                        : esc_html__('Unlicensed', 'ezit-core')); ?>
                            </span>
                            <?php if (EzIT_Updater::is_pinned($slug)) : ?>
                                <span class="ezit-badge ezit-badge--pinned"
                                      title="<?php esc_attr_e('Updates paused at this version — automatic and forced installs skip this product until you resume them', 'ezit-core'); ?>">
                                    <?php esc_html_e('Pinned', 'ezit-core'); ?>
                                </span>
                                <a class="ezit-chip__pin is-resume" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ezit_unpin&slug=' . $slug . '&from=marketplace'), 'ezit_unpin', 'ezit_unpin_nonce')); ?>"
                                   title="<?php esc_attr_e('Resume automatic and forced updates', 'ezit-core'); ?>">
                                    <?php esc_html_e('Resume', 'ezit-core'); ?>
                                </a>
                            <?php else : ?>
                                <a class="ezit-chip__pin" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ezit_pin&slug=' . $slug . '&from=marketplace'), 'ezit_pin', 'ezit_pin_nonce')); ?>"
                                   data-confirm-title="<?php esc_attr_e('Pin updates?', 'ezit-core'); ?>"
                                   data-confirm="<?php esc_attr_e('Automatic and forced updates pause at this version until you resume them.', 'ezit-core'); ?>"
                                   data-confirm-ok="<?php esc_attr_e('Pin', 'ezit-core'); ?>"
                                   title="<?php esc_attr_e('Hold this version — automatic and forced updates pause until you resume them', 'ezit-core'); ?>">
                                    <?php esc_html_e('Pin updates', 'ezit-core'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="ezit-muted ezit-pin-hint">
                    <?php esc_html_e('Hover a product or the Installed badge to Pin it — pinning holds the current version and pauses automatic and forced updates until you resume them.', 'ezit-core'); ?>
                </p>
            <?php endif; ?>

            <h4 class="ezit-sec-label"><?php esc_html_e('Ez IT Marketplace', 'ezit-core'); ?></h4>

            <?php if (! $catalog) : ?>
                <p class="ezit-muted">
                    <?php esc_html_e('Nothing published yet — listings appear here once the license server catalog goes live.', 'ezit-core'); ?>
                    <?php esc_html_e('(Listings restricted to specific licenses only appear on sites holding one.)', 'ezit-core'); ?>
                </p>
            <?php else :
                // Version modals render OUTSIDE the card tree — an
                // ancestor transform (card hover lift) would make
                // position:fixed resolve against the card, not the
                // viewport. The open label stays inside the card and
                // toggles the checkbox by id.
                $ezit_vm_modals = '';
            ?>
                <div class="ezit-cards">
                    <?php
                    $registered = $this->core->products();
                    // EZIT_CORE_PRODUCT_ID can be blank — credentials then
                    // resolve lazily from the free catalog (self_credentials).
                    // Without this fallback the core card matches no product,
                    // sorts last, and loses its is_self flag.
                    $self_pid   = EZIT_CORE_PRODUCT_ID !== ''
                        ? EZIT_CORE_PRODUCT_ID
                        : (string) ($this->core->self_credentials()['product_id'] ?? '');
                    $items      = $catalog;
                    // Core has no slug in $registered but IS in the catalog —
                    // keep the ezit-core card and flag its slug for later.
                    $items = array_values($items);
                    ?>

                    <?php
                    // Order cards like the "Installed on this site" strip:
                    // registered-product order first, then everything else in
                    // catalog order (PHP 8 sorts are stable). Resolves the same
                    // product_id -> local slug the card loop resolves below.
                    $local_slug_of = static function (array $it) use ($registered, $self_pid): ?string {
                        if (($it['type'] ?? '') === 'plugin' && ($it['product_id'] ?? '') === $self_pid) {
                            return 'ezit-core';
                        }
                        $pid = (string) ($it['product_id'] ?? '');
                        if ($pid === '') {
                            return null;
                        }
                        foreach ($registered as $s => $p) {
                            if (($p['product_id'] ?? '') === $pid) {
                                return $s;
                            }
                        }
                        return null;
                    };
                    $reg_order = array_flip(array_keys($this->core->products()));
                    usort($items, static function (array $a, array $b) use ($local_slug_of, $reg_order): int {
                        $sa = $local_slug_of($a);
                        $sb = $local_slug_of($b);
                        $ra = $sa !== null && isset($reg_order[$sa]) ? $reg_order[$sa] : PHP_INT_MAX;
                        $rb = $sb !== null && isset($reg_order[$sb]) ? $reg_order[$sb] : PHP_INT_MAX;
                        return $ra <=> $rb;
                    });
                    ?>

                    <?php foreach ($items as $item) :
                        $slug      = (string) ($item['slug'] ?? '');
                        $free      = ! empty($item['free']);
                        // Match the locally registered product (same product_id)
                        // so we can flag when the catalog is ahead.
                        $local = null;
                        $local_slug = null;
                        foreach ($products as $lslug => $lp) {
                            if (($lp['product_id'] ?? '') !== '' && ($lp['product_id'] ?? '') === ($item['product_id'] ?? '')) {
                                $local = $lp;
                                $local_slug = $lslug;
                                break;
                            }
                        }
                        // Local truth beats the server flag: a product
                        // registered on THIS site is installed, license or
                        // not. EzIT Core is self-evident — it renders this
                        // page — even when the self-product isn't wired up
                        // (constants unset), so catch its listing by slug too.
                        $is_self   = ($item['type'] ?? '') === 'plugin' && $slug === 'ezit-core';
                        $installed = ! empty($item['installed']) || $local !== null || $is_self;
                        $local_ver = $local !== null ? (string) ($local['version'] ?? '0')
                                   : ($is_self ? EZIT_CORE_VERSION : null);
                        if ($local_slug === null && $is_self) {
                            $local_slug = 'ezit-core'; // self always registers
                        }
                        $pinned    = $local_slug !== null && EzIT_Updater::is_pinned($local_slug);
                        $newer = $local_ver !== null
                            && version_compare((string) ($item['version'] ?? '0'), $local_ver, '>');
                        ?>
                        <div class="ezit-card">
                            <div class="ezit-card__shot">
                            <?php if (! empty($item['thumb'])) : ?>
                                <img class="ezit-card-thumb" src="<?php echo esc_url($item['thumb']); ?>" alt="">
                            <?php else :
                                // No thumbnail — render a branded tile so the
                                // card keeps its 16:9 top block instead of
                                // collapsing next to cards that have shots.
                                ?>
                                <div class="ezit-card-thumb ezit-card-thumb--empty" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
                                    <?php if (trim((string) ($item['name'] ?? '')) !== '') : ?><span><?php echo esc_html((string) $item['name']); ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($local_slug !== null) : ?>
                                <label class="ezit-card__veropen" for="<?php echo esc_attr('ezit-vm-' . $local_slug); ?>"
                                       title="<?php esc_attr_e('Install any published release — picks older versions pin against forced updates', 'ezit-core'); ?>">
                                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                                    <?php esc_html_e('Version history', 'ezit-core'); ?>
                                    <?php if ($pinned) : ?><em><?php esc_html_e('· pinned', 'ezit-core'); ?></em><?php endif; ?>
                                </label>
                            <?php endif; ?>
                            </div>
                            <strong><?php echo esc_html($item['name'] ?? '—'); ?></strong>
                            <span class="ezit-meta">
                                <?php echo esc_html(($item['type'] ?? 'product') . ' · v' . (($item['version'] ?? '') ?: '—') . ' · ' . ($item['category'] ?? 'generic')); ?>
                            </span>
                            <?php if (! empty($item['excerpt'])) : ?>
                                <p class="ezit-card-excerpt"><?php echo esc_html($item['excerpt']); ?></p>
                            <?php endif; ?>
                            <div class="ezit-card__foot">
                                <?php
                                // Installed + unpinned: the badge itself is the
                                // pin control — hover swaps INSTALLED for Pin.
                                if ($installed && $local_slug !== null && ! $pinned) : ?>
                                    <a class="ezit-badge-swap" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ezit_pin&slug=' . $local_slug . '&from=marketplace'), 'ezit_pin', 'ezit_pin_nonce')); ?>"
                                       data-confirm-title="<?php esc_attr_e('Pin updates?', 'ezit-core'); ?>"
                                       data-confirm="<?php esc_attr_e('Automatic and forced updates pause at this version until you resume them.', 'ezit-core'); ?>"
                                       data-confirm-ok="<?php esc_attr_e('Pin', 'ezit-core'); ?>"
                                       title="<?php esc_attr_e('Hold this version — automatic and forced updates pause until you resume them', 'ezit-core'); ?>">
                                        <span class="ezit-badge is-active ezit-swap__idle"><?php esc_html_e('Installed', 'ezit-core'); ?></span>
                                        <span class="ezit-badge ezit-badge--pin ezit-swap__on"><?php esc_html_e('Pin updates', 'ezit-core'); ?></span>
                                    </a>
                                <?php else : ?>
                                    <span class="ezit-badge <?php echo $installed ? 'is-active' : ($free ? 'is-free' : 'is-paid'); ?>">
                                        <?php
                                        echo $installed ? esc_html__('Installed', 'ezit-core')
                                            : ($free ? esc_html__('Free', 'ezit-core')
                                            : esc_html(isset($item['price']) ? '$' . number_format((float) $item['price'], 2) : __('Paid', 'ezit-core')));
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($pinned) : ?>
                                    <span class="ezit-badge ezit-badge--pinned"
                                          title="<?php esc_attr_e('Updates paused at this version — automatic and forced installs skip this product until you resume them', 'ezit-core'); ?>">
                                        <?php esc_html_e('Pinned', 'ezit-core'); ?>
                                    </span>
                                    <?php if ($newer) : ?>
                                        <span class="ezit-badge is-update">
                                            <?php printf(esc_html__('Update &rarr; v%s', 'ezit-core'), esc_html((string) $item['version'])); ?>
                                        </span>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                              data-confirm-title="<?php echo esc_attr(sprintf(__('Update to v%s?', 'ezit-core'), (string) $item['version'])); ?>"
                                              data-confirm="<?php echo esc_attr__('Installing the newest release un-pins this product and resumes auto-updates.', 'ezit-core'); ?>"
                                              data-confirm-ok="<?php esc_attr_e('Update', 'ezit-core'); ?>">
                                            <input type="hidden" name="action" value="ezit_market_install">
                                            <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                            <input type="hidden" name="product_id" value="<?php echo esc_attr((string) ($item['product_id'] ?? '')); ?>">
                                            <input type="hidden" name="from" value="marketplace">
                                            <?php wp_nonce_field('ezit_market_install', 'ezit_market_nonce'); ?>
                                            <button type="submit" class="ezit-btn" data-busy-label="<?php esc_attr_e('Updating…', 'ezit-core'); ?>"><?php esc_html_e('Update', 'ezit-core'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <a class="ezit-resume" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ezit_unpin&slug=' . $local_slug . '&from=marketplace'), 'ezit_unpin', 'ezit_unpin_nonce')); ?>">
                                        <?php esc_html_e('Resume updates', 'ezit-core'); ?>
                                    </a>
                                <?php elseif ($newer) : ?>
                                    <span class="ezit-badge is-update">
                                        <?php printf(esc_html__('Update &rarr; v%s', 'ezit-core'), esc_html((string) $item['version'])); ?>
                                    </span>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="ezit_market_install">
                                        <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) ($item['product_id'] ?? '')); ?>">
                                        <input type="hidden" name="from" value="marketplace">
                                        <?php wp_nonce_field('ezit_market_install', 'ezit_market_nonce'); ?>
                                        <button type="submit" class="ezit-btn" data-busy-label="<?php esc_attr_e('Updating…', 'ezit-core'); ?>"><?php esc_html_e('Update', 'ezit-core'); ?></button>
                                    </form>
                                <?php elseif (! $installed && $free) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="ezit_market_install">
                                        <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) ($item['product_id'] ?? '')); ?>">
                                        <input type="hidden" name="from" value="marketplace">
                                        <?php wp_nonce_field('ezit_market_install', 'ezit_market_nonce'); ?>
                                        <button type="submit" class="ezit-btn" data-busy-label="<?php esc_attr_e('Installing…', 'ezit-core'); ?>"><?php esc_html_e('Install', 'ezit-core'); ?></button>
                                    </form>
                                <?php elseif (! $installed) : ?>
                                    <button class="ezit-btn ezit-btn--ghost" disabled title="<?php esc_attr_e('Checkout ships with Phase 5', 'ezit-core'); ?>">
                                        <?php esc_html_e('Purchase', 'ezit-core'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php
                            // Version picker — a modal per card (checkbox
                            // toggle, zero JS). Picking anything but the
                            // newest release pins the product so a forced
                            // update can't roll it straight back.
                            if ($local_slug !== null) :
                                $releases  = $this->core->updater()->versions_for($local_slug);
                                $ver_err   = $this->core->updater()->versions_error();
                                $vm_id     = 'ezit-vm-' . $local_slug;
                            ?>
                                <?php ob_start(); ?>
                                <input type="checkbox" class="ezit-vm__toggle" id="<?php echo esc_attr($vm_id); ?>">
                                <div class="ezit-vm">
                                    <label class="ezit-vm__backdrop" for="<?php echo esc_attr($vm_id); ?>"></label>
                                    <div class="ezit-vm__card">
                                        <div class="ezit-vm__head">
                                            <h3><?php
                                                /* translators: %s: product name */
                                                printf(esc_html__('Version history — %s', 'ezit-core'), esc_html($item['name'] ?? $slug)); ?></h3>
                                            <label class="ezit-vm__x" for="<?php echo esc_attr($vm_id); ?>" aria-label="<?php esc_attr_e('Close', 'ezit-core'); ?>">&times;</label>
                                        </div>
                                        <p class="ezit-vm__sub">
                                            <?php printf(esc_html__('Running v%s', 'ezit-core'), esc_html($local_ver ?? '?')); ?>
                                            <?php if ($pinned) : ?>
                                                <span class="ezit-badge ezit-badge--pinned"><?php esc_html_e('Pinned — auto-updates suspended', 'ezit-core'); ?></span>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ezit_unpin&slug=' . $local_slug . '&from=marketplace'), 'ezit_unpin', 'ezit_unpin_nonce')); ?>"><?php esc_html_e('Resume updates', 'ezit-core'); ?></a>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (! $releases) : ?>
                                            <p class="ezit-vm__empty">
                                                <?php echo $ver_err !== ''
                                                    /* translators: %s: the API error message */
                                                    ? sprintf(esc_html__('Release history unavailable: %s', 'ezit-core'), esc_html($ver_err))
                                                    : esc_html__('No published releases yet.', 'ezit-core'); ?>
                                            </p>
                                        <?php else : ?>
                                        <?php
                                        // A running build that isn't a published release (a local
                                        // build, or a release published after this site installed)
                                        // would otherwise leave the list with no "Current" row.
                                        $rel_has_local = false;
                                        foreach ($releases as $r) {
                                            if (version_compare((string) ($r['version'] ?? ''), $local_ver ?: '0', '==')) {
                                                $rel_has_local = true;
                                                break;
                                            }
                                        }
                                        $ezit_vm_local_row = function () use ($local_ver): void {
                                            ?>
                                            <li class="ezit-vm__row is-current">
                                                <div class="ezit-vm__ver">
                                                    <strong><?php echo esc_html('v' . $local_ver); ?></strong>
                                                    <span class="ezit-meta"><?php esc_html_e('this site', 'ezit-core'); ?></span>
                                                </div>
                                                <div class="ezit-vm__sum"><?php esc_html_e('Currently installed — a local build or a release that was never published to the server.', 'ezit-core'); ?></div>
                                                <div class="ezit-vm__act"><span class="ezit-badge is-active"><?php esc_html_e('Current', 'ezit-core'); ?></span></div>
                                            </li>
                                            <?php
                                        };
                                        $ezit_vm_local_shown = false;
                                        ?>
                                        <ul class="ezit-vm__list">
                                            <?php foreach ($releases as $i => $rel) :
                                                $rel_ver = (string) ($rel['version'] ?? '');
                                                if (! $rel_has_local && ! $ezit_vm_local_shown && $local_ver !== null
                                                    && version_compare($local_ver, $rel_ver, '>')) {
                                                    $ezit_vm_local_row();
                                                    $ezit_vm_local_shown = true;
                                                }
                                                $rel_cur  = $rel_ver === $local_ver;
                                                $rel_tags = [];
                                                if ($i === 0) { $rel_tags[] = __('latest', 'ezit-core'); }
                                                if (! empty($rel['forced'])) { $rel_tags[] = __('forced', 'ezit-core'); }
                                            ?>
                                                <li class="ezit-vm__row<?php echo $rel_cur ? ' is-current' : ''; ?>">
                                                    <div class="ezit-vm__ver">
                                                        <strong>v<?php echo esc_html($rel_ver); ?></strong>
                                                        <?php if (($rel['release_date'] ?? '') !== '') : ?>
                                                            <span class="ezit-meta"><?php echo esc_html($rel['release_date']); ?></span>
                                                        <?php endif; ?>
                                                        <?php foreach ($rel_tags as $t) : ?>
                                                            <span class="ezit-badge is-tag"><?php echo esc_html($t); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="ezit-vm__sum"><?php echo esc_html((string) ($rel['summary'] ?? '')); ?></div>
                                                    <div class="ezit-vm__act">
                                                        <?php if ($rel_cur) : ?>
                                                            <span class="ezit-badge is-active"><?php esc_html_e('Current', 'ezit-core'); ?></span>
                                                        <?php else : ?>
                                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                                                  data-confirm-title="<?php echo esc_attr(sprintf(__('Install v%s?', 'ezit-core'), $rel_ver)); ?>"
                                                                  data-confirm="<?php echo esc_attr(__('This version replaces the current files. Anything older than the newest release pins the product and suspends auto-updates.', 'ezit-core')); ?>"
                                                                  data-confirm-ok="<?php echo version_compare($rel_ver, $local_ver ?: '0', '<') ? esc_attr__('Downgrade', 'ezit-core') : esc_attr__('Install', 'ezit-core'); ?>">
                                                                <input type="hidden" name="action" value="ezit_version">
                                                                <input type="hidden" name="slug" value="<?php echo esc_attr($local_slug); ?>">
                                                                <input type="hidden" name="update_id" value="<?php echo esc_attr((string) ($rel['update_id'] ?? '')); ?>">
                                                                <?php wp_nonce_field('ezit_version', 'ezit_version_nonce'); ?>
                                                                <button type="submit" class="ezit-btn">
                                                                    <?php echo version_compare($rel_ver, $local_ver ?: '0', '<')
                                                                        ? esc_html__('Downgrade', 'ezit-core')
                                                                        : esc_html__('Install', 'ezit-core'); ?>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                            <?php
                                            // Older than everything published — sits at the bottom.
                                            if (! $rel_has_local && ! $ezit_vm_local_shown && $local_ver !== null) {
                                                $ezit_vm_local_row();
                                            }
                                            ?>
                                        </ul>
                                        <?php endif; ?>
                                        <p class="ezit-vm__note"><?php esc_html_e('Choosing a release older than the newest published one pins this product — forced and automatic updates pause until you resume them.', 'ezit-core'); ?></p>
                                    </div>
                                </div>
                                <?php $ezit_vm_modals .= ob_get_clean(); ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
                // Print modals at admin_footer — top-level in the DOM, so
                // no animated-panel stacking context can tuck them behind
                // the sticky top bar.
                if ($ezit_vm_modals !== '') {
                    add_action('admin_footer', static function () use ($ezit_vm_modals): void {
                        echo $ezit_vm_modals; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — fully escaped at build time
                    }, 5);
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
