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
        add_filter('upgrader_pre_download', [$this, 'licensed_download'], 10, 4);
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
                $transient->response[$slug] = [
                    'theme'        => $slug,
                    'new_version'  => $info['version'],
                    'url'          => admin_url('admin.php?page=ezit-core'),
                    'package'      => 'ezit://' . $slug . '/' . $info['update_id'],
                    'requires'     => '',
                    'requires_php' => '',
                ];
            }
        }

        return $transient;
    }

    /**
     * /api/check_update — cached per product so the transient filter
     * doesn't hit the server on every admin page load.
     */
    private function check(string $slug, array $product): ?array
    {
        if (isset($this->updates[$slug])) {
            return $this->updates[$slug];
        }

        $lic = $this->licensing->license($slug);
        if (! $lic || empty($lic['license_file']) || $product['product_id'] === '') {
            return $this->updates[$slug] = null;
        }

        $cache_key = 'ezit_update_' . $slug;
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $this->updates[$slug] = ($cached === 'none') ? null : $cached;
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

        if (! $product || ! $lic || empty($lic['license_file'])) {
            return new WP_Error('ezit_unlicensed', 'This product is not licensed.');
        }

        $file = EzIT_API::download('download_update/main/' . rawurlencode($update_id), $product, [
            'product_id'   => $product['product_id'],
            'license_file' => $lic['license_file'],
        ]);

        return $file; // path or WP_Error — WP streams it into the upgrader
    }
}
