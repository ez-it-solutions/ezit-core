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

        $name    = (string) ($item['name'] ?? $slug);
        $updated = $local !== null;
        if ($type === 'plugin' && method_exists($upgrader, 'plugin_info')) {
            $file = $upgrader->plugin_info();
            if ($file && ! is_plugin_active($file)) {
                activate_plugin($file); // runs the product's own bootstrap → it self-registers
            }
            return ['status' => true, 'message' => $updated
                /* translators: %s: product name */
                ? sprintf(__('%s updated.', 'ezit-core'), $name)
                /* translators: %s: product name */
                : sprintf(__('%s installed and activated — it self-registers now, license it under the Licenses tab.', 'ezit-core'), $name)];
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
        // 5-minute freshness window applies, so "Fetched N ago" is a
        // real age instead of always "1 second ago".
        $fresh    = isset($_GET['ezit_refresh']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended.NonceVerification — verified below
            && wp_verify_nonce(sanitize_key($_GET['_wpnonce'] ?? ''), 'ezit_refresh'); // phpcs:ignore
        $catalog  = $this->catalog($fresh);
        $licensing = $this->core->licensing();
        $fetched  = $this->catalog_fetched_at();
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
                            <span class="ezit-badge <?php echo $licensing->is_active($slug) ? 'is-active' : 'is-inactive'; ?>">
                                <?php echo $licensing->is_active($slug)
                                    ? esc_html__('Licensed', 'ezit-core')
                                    : esc_html__('Unlicensed', 'ezit-core'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2 class="ezit-market-head">
                <?php esc_html_e('Ez IT Marketplace', 'ezit-core'); ?>
                <span class="ezit-market-actions">
                    <span class="ezit-catalog-at">
                        <?php if ($fetched > 0) : ?>
                            <span class="ezit-catalog-at__age"
                                  data-ts="<?php echo (int) $fetched; ?>"
                                  data-label="<?php esc_attr_e('Fetched', 'ezit-core'); ?>"
                                  title="<?php echo esc_attr(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $fetched)); ?>">
                                <?php
                                printf(
                                    /* translators: %s: human-readable time difference */
                                    esc_html__('Fetched %s ago', 'ezit-core'),
                                    esc_html(human_time_diff($fetched, time()))
                                );
                                ?>
                            </span>
                        <?php else : ?>
                            <?php esc_html_e('Never fetched', 'ezit-core'); ?>
                        <?php endif; ?>
                        <?php if ($this->stale) : ?>
                            <?php esc_html_e('— showing the cached copy (server unreachable)', 'ezit-core'); ?>
                        <?php endif; ?>
                    </span>
                    <a class="ezit-btn ezit-btn--ghost" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=ezit-core&tab=marketplace&ezit_refresh=1'), 'ezit_refresh')); ?>">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                        <?php esc_html_e('Refresh', 'ezit-core'); ?>
                    </a>
                </span>
            </h2>
            <?php if (! $catalog) : ?>
                <p class="ezit-muted">
                    <?php esc_html_e('Nothing published yet — listings appear here once the license server catalog goes live.', 'ezit-core'); ?>
                    <?php esc_html_e('(Listings restricted to specific licenses only appear on sites holding one.)', 'ezit-core'); ?>
                </p>
            <?php else : ?>
                <div class="ezit-cards">
                    <?php foreach ($catalog as $item) :
                        $slug      = (string) ($item['slug'] ?? '');
                        $installed = ! empty($item['installed']);
                        $free      = ! empty($item['free']);
                        // Match the locally registered product (same product_id)
                        // so we can flag when the catalog is ahead.
                        $local = null;
                        foreach ($products as $lp) {
                            if (($lp['product_id'] ?? '') !== '' && ($lp['product_id'] ?? '') === ($item['product_id'] ?? '')) {
                                $local = $lp;
                                break;
                            }
                        }
                        $newer = $local && version_compare((string) ($item['version'] ?? '0'), (string) ($local['version'] ?? '0'), '>');
                        ?>
                        <div class="ezit-card">
                            <?php if (! empty($item['thumb'])) : ?>
                                <img class="ezit-card-thumb" src="<?php echo esc_url($item['thumb']); ?>" alt="">
                            <?php endif; ?>
                            <strong><?php echo esc_html($item['name'] ?? '—'); ?></strong>
                            <span class="ezit-meta">
                                <?php echo esc_html(($item['type'] ?? 'product') . ' · v' . (($item['version'] ?? '') ?: '—') . ' · ' . ($item['category'] ?? 'generic')); ?>
                            </span>
                            <?php if (! empty($item['excerpt'])) : ?>
                                <p class="ezit-card-excerpt"><?php echo esc_html($item['excerpt']); ?></p>
                            <?php endif; ?>
                            <div class="ezit-card__foot">
                                <span class="ezit-badge <?php echo $installed ? 'is-active' : ($free ? 'is-free' : 'is-paid'); ?>">
                                    <?php
                                    echo $installed ? esc_html__('Installed', 'ezit-core')
                                        : ($free ? esc_html__('Free', 'ezit-core')
                                        : esc_html(isset($item['price']) ? '$' . number_format((float) $item['price'], 2) : __('Paid', 'ezit-core')));
                                    ?>
                                </span>
                                <?php if ($newer) : ?>
                                    <span class="ezit-badge is-update">
                                        <?php printf(esc_html__('Update &rarr; v%s', 'ezit-core'), esc_html((string) $item['version'])); ?>
                                    </span>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="ezit_market_install">
                                        <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) ($item['product_id'] ?? '')); ?>">
                                        <input type="hidden" name="from" value="marketplace">
                                        <?php wp_nonce_field('ezit_market_install', 'ezit_market_nonce'); ?>
                                        <button type="submit" class="ezit-btn"><?php esc_html_e('Update', 'ezit-core'); ?></button>
                                    </form>
                                <?php elseif (! $installed && $free) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <input type="hidden" name="action" value="ezit_market_install">
                                        <input type="hidden" name="slug" value="<?php echo esc_attr($slug); ?>">
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) ($item['product_id'] ?? '')); ?>">
                                        <input type="hidden" name="from" value="marketplace">
                                        <?php wp_nonce_field('ezit_market_install', 'ezit_market_nonce'); ?>
                                        <button type="submit" class="ezit-btn"><?php esc_html_e('Install', 'ezit-core'); ?></button>
                                    </form>
                                <?php elseif (! $installed) : ?>
                                    <button class="ezit-btn ezit-btn--ghost" disabled title="<?php esc_attr_e('Checkout ships with Phase 5', 'ezit-core'); ?>">
                                        <?php esc_html_e('Purchase', 'ezit-core'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
