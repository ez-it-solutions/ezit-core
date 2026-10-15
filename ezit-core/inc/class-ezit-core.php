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
     *              product_id?:string,api_key?:string,gated?:bool} $args
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

    /** The license-server base URL; filterable + option override. */
    public function api_url(): string
    {
        $saved = get_option('ezit_api_url', '');
        $url   = $saved !== '' ? $saved : EZIT_API_URL;
        return untrailingslashit(apply_filters('ezit_api_url', $url));
    }
}
