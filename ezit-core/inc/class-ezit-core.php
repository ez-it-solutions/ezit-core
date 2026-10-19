<?php
/**
 * EzIT_Core — the product registry. Every theme/plugin/product announces
 * itself here; the licensing, updater and marketplace modules all read
 * from this single list. Core never hard-codes a theme name.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Core
{
    private static ?EzIT_Core $instance = null;

    /** @var array<string,array> slug => product args */
    private array $products = [];

    public static function instance(): EzIT_Core
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a licensable product. Call from a theme's functions.php
     * (top-level is fine) or on the `ezit_core_ready` hook.
     *
     * @param array{slug:string,type?:string,name:string,version?:string,
     *              product_id?:string,api_key?:string,gated?:bool,
     *              deactivate_on_switch?:bool} $args
     */
    public function register_product(array $args): void
    {
        $args = wp_parse_args($args, [
            'type'         => 'theme',
            'version'      => '',
            'product_id'   => '',
            'api_key'      => '',
            'license_code' => '',     // optional — pre-fills the activate form
            'marketplace'  => true,   // show on the Marketplace page
            'gated'        => true,   // unlicensed = site shows the UC gate
            // Theme-only: release the license seat when the theme is
            // switched away (try-before-buy/demo deployments).
            'deactivate_on_switch' => false,
            'uc'           => [],     // UC page defaults: accent, bg, logo, countdown
        ]);

        if (empty($args['slug'])) {
            return;
        }

        $this->products[sanitize_key($args['slug'])] = $args;
    }

    /** @return array<string,array> */
    public function products(): array
    {
        return $this->products;
    }

    public function product(string $slug): ?array
    {
        return $this->products[sanitize_key($slug)] ?? null;
    }

    /** Shared licensing module (one cron hook, one source of truth). */
    private ?EzIT_Licensing $licensing = null;

    public function licensing(): EzIT_Licensing
    {
        return $this->licensing ??= new EzIT_Licensing($this);
    }

    /** Shared updater — one instance feeds WP's update transients AND
     *  answers "is there an update?" for our own admin tabs. */
    private ?EzIT_Updater $updater = null;

    public function updater(): EzIT_Updater
    {
        return $this->updater ??= new EzIT_Updater($this);
    }

    /**
     * EzIT Core's own product credentials on the license server.
     *
     * Constants (wp-config.php or packaged) win; otherwise resolve from
     * the unauthenticated free catalog — GET /api/catalog/ezit-core
     * returns product_id, and free published items publish their
     * api_key. Cached 12h; a 5-minute negative entry keeps a downed
     * server from stalling every request.
     *
     * @return array{product_id:string, api_key:string}
     */
    public function self_credentials(): array
    {
        if (EZIT_CORE_PRODUCT_ID !== '' && EZIT_CORE_PRODUCT_KEY !== '') {
            return [
                'product_id' => EZIT_CORE_PRODUCT_ID,
                'api_key'    => EZIT_CORE_PRODUCT_KEY,
            ];
        }

        $cached = get_transient('ezit_self_cred');
        if ($cached === 'none') {
            return ['product_id' => '', 'api_key' => ''];
        }
        if (is_array($cached)) {
            return $cached;
        }

        $creds = ['product_id' => '', 'api_key' => ''];
        $res = wp_remote_get($this->api_url() . '/api/catalog/ezit-core', [
            'timeout' => 10,
            'headers' => [
                'LB-URL'  => home_url(),
                'LB-LANG' => get_locale() ?: 'en',
            ],
        ]);
        if (! is_wp_error($res) && wp_remote_retrieve_response_code($res) < 400) {
            $json = json_decode(wp_remote_retrieve_body($res), true);
            $item = is_array($json['item'] ?? null) ? $json['item'] : [];
            if (($item['product_id'] ?? '') !== '' && ($item['api_key'] ?? '') !== '') {
                $creds = [
                    'product_id' => (string) $item['product_id'],
                    'api_key'    => (string) $item['api_key'],
                ];
            }
        }
        set_transient(
            'ezit_self_cred',
            $creds['api_key'] !== '' ? $creds : 'none',
            $creds['api_key'] !== '' ? 12 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS
        );
        return $creds;
    }

    /**
     * Bootstrap URL — the hardcoded/option address the site can always
     * reach home on. Server config's api_base overrides this at runtime
     * once synced (see EzIT_Config).
     */
    public function bootstrap_url(): string
    {
        $saved = get_option('ezit_api_url', '');
        $url   = $saved !== '' ? $saved : EZIT_API_URL;
        return untrailingslashit($url);
    }

    /**
     * Effective license-server base URL. Published server config wins —
     * that's the point of get_config: repoint every site by publishing a
     * new config version, no plugin redeploy.
     */
    public function api_url(): string
    {
        $base = class_exists('EzIT_Config') ? (EzIT_Config::api_base() ?? $this->bootstrap_url())
                                            : $this->bootstrap_url();
        return untrailingslashit(apply_filters('ezit_api_url', $base));
    }
}
