<?php
/**
 * EzIT_Marketplace — the future storefront.
 *
 * Phase 1: renders the products registered on THIS site plus a remote
 * catalog fetch. The catalog endpoint (`GET /api/catalog`) doesn't exist
 * server-side yet — the fetch fails soft, caches an empty list for an
 * hour, and the UI shows "coming soon" instead of errors. When the
 * endpoint ships, this page lights up with no core changes.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Marketplace
{
    private EzIT_Core $core;

    public function __construct(EzIT_Core $core)
    {
        $this->core = $core;
    }

    /**
     * Remote catalog — array of items or [] (cached; never WP_Error so the
     * UI can render uniformly).
     */
    public function catalog(): array
    {
        $cached = get_transient('ezit_catalog');
        if ($cached !== false) {
            return is_array($cached) ? $cached : [];
        }

        $res = wp_remote_get($this->core->api_url() . '/api/catalog', [
            'timeout' => 12,
            'headers' => [
                'LB-URL'  => home_url(),
                'LB-LANG' => get_locale() ?: 'en',
            ],
        ]);

        $items = [];
        if (! is_wp_error($res) && wp_remote_retrieve_response_code($res) < 400) {
            $json  = json_decode(wp_remote_retrieve_body($res), true);
            $items = is_array($json['items'] ?? null) ? $json['items'] : [];
        }

        // short cache — a 404/500 shouldn't spam the server on every page load
        set_transient('ezit_catalog', $items, HOUR_IN_SECONDS);
        return $items;
    }

    /** Render the marketplace grid (called by EzIT_Admin's page callback). */
    public function render(): void
    {
        $products = $this->core->products();
        $catalog  = $this->catalog();
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
                            <span class="ezit-badge <?php echo $licensing->is_active($slug) ? 'is-active' : 'is-inactive'; ?>">
                                <?php echo $licensing->is_active($slug)
                                    ? esc_html__('Licensed', 'ezit-core')
                                    : esc_html__('Unlicensed', 'ezit-core'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e('Ez IT Marketplace', 'ezit-core'); ?></h2>
            <?php if (! $catalog) : ?>
                <p class="ezit-muted">
                    <?php esc_html_e('The remote catalog ships with the Phase 3 server update — themes, plugins and products will appear here automatically.', 'ezit-core'); ?>
                </p>
            <?php else : ?>
                <div class="ezit-cards">
                    <?php foreach ($catalog as $item) : ?>
                        <div class="ezit-card">
                            <strong><?php echo esc_html($item['name'] ?? '—'); ?></strong>
                            <span class="ezit-meta"><?php echo esc_html($item['type'] ?? ''); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
