<?php
/**
 * EzIT_Themes — theme inventory + Ez IT product detection.
 *
 * Two detection layers (see ROADMAP.md "Product manifest"):
 *   1. runtime — anything registered via register_product()
 *   2. filesystem — an `EzIT Product: {product_id}` header in style.css
 *      (themes) or the plugin header (plugins), so installed-but-inactive
 *      or not-yet-registered products still surface.
 *
 * Also owns the footer-credit contract: theme mods `ezit_credit_enabled`
 * and `ezit_credit_text` — WP storage, so the values travel with the
 * theme and the Customizer can expose them too.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Themes
{
    /** Theme-mod keys for the footer credit (read by product themes). */
    public const CREDIT_ON  = 'ezit_credit_enabled';
    public const CREDIT_TXT = 'ezit_credit_text';

    /** Custom header products stamp on themselves for detection. */
    public const HEADER = 'EzIT Product';

    /**
     * Register the custom header so wp_get_themes()/get_file_data()
     * expose it — `extra_theme_headers` applies at read time, so this
     * just needs to run before a tab template asks for it.
     */
    public static function boot(): void
    {
        add_filter('extra_theme_headers', function (array $headers): array {
            $headers[] = self::HEADER;
            return $headers;
        });
    }

    /**
     * Every installed theme, Ez IT themes first. Each row:
     * stylesheet, name, version, author, screenshot, is_active,
     * ezit (product?), registered, licensed.
     */
    public static function inventory(EzIT_Core $core): array
    {
        $products  = $core->products();
        $licensing = $core->licensing();
        $active    = get_stylesheet();
        $rows      = [];

        foreach (wp_get_themes() as $stylesheet => $theme) {
            $pid = trim((string) $theme->get(self::HEADER));
            // Registry keys are sanitize_key()'d — "Trezise Insurance"
            // registers under `treziseinsurance`.
            $reg = $products[sanitize_key($stylesheet)] ?? null;

            $rows[] = [
                'stylesheet' => $stylesheet,
                'name'       => $theme->display('Name'),
                'version'    => (string) $theme->get('Version'),
                'author'     => (string) $theme->get('Author'),
                'screenshot' => $theme->get_screenshot(),
                'is_active'  => $stylesheet === $active,
                'ezit'       => $pid !== '' || $reg !== null,
                'registered' => $reg !== null,
                'licensed'   => $reg !== null && $licensing->is_active($stylesheet),
            ];
        }

        // Active first, then Ez IT products, then everything else (a-z).
        usort($rows, static function (array $a, array $b): int {
            if ($a['is_active'] !== $b['is_active']) {
                return $a['is_active'] ? -1 : 1;
            }
            if ($a['ezit'] !== $b['ezit']) {
                return $a['ezit'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /** Read a product's ezit.json manifest (empty array when absent). */
    private static function manifest(string $dir): array
    {
        $file = trailingslashit($dir) . 'ezit.json';
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true); // phpcs:ignore
        return is_array($data) ? $data : [];
    }

    /**
     * All detected Ez IT products — core itself, plugins and themes —
     * for the About tab inventory.
     */
    public static function detected(EzIT_Core $core): array
    {
        $products  = $core->products();
        $licensing = $core->licensing();
        $items     = [];

        // Core itself is always present.
        $items[] = [
            'slug'       => 'ezit-core',
            'type'       => 'plugin',
            'name'       => 'Ez IT Core',
            'version'    => EZIT_CORE_VERSION,
            'product_id' => '',
            'vertical'   => 'core',
            'state'      => 'active',
            'registered' => false,
            'licensed'   => null, // core isn't licensed itself
        ];

        // Plugins carrying the header.
        if (is_admin() && function_exists('get_plugins')) {
            foreach (get_plugins() as $file => $data) {
                if ($file === plugin_basename(EZIT_CORE_FILE)) {
                    continue; // core, already listed
                }
                $meta = get_file_data(WP_PLUGIN_DIR . '/' . $file, [
                    'pid' => self::HEADER,
                ]);
                $pid  = trim($meta['pid'] ?? '');
                if ($pid === '') {
                    continue;
                }
                $slug = dirname($file) === '.' ? basename($file, '.php') : dirname($file);
                $reg  = $products[$slug] ?? null;
                $items[] = [
                    'slug'       => $slug,
                    'type'       => 'plugin',
                    'name'       => $data['Name'] ?? $slug,
                    'version'    => $data['Version'] ?? '',
                    'product_id' => $pid,
                    'state'      => is_plugin_active($file) ? 'active' : 'installed',
                    'registered' => $reg !== null,
                    'licensed'   => $reg !== null ? $licensing->is_active($slug) : null,
                ];
            }
        }

        // Themes carrying the header (or registered even without it).
        foreach (wp_get_themes() as $stylesheet => $theme) {
            $pid = trim((string) $theme->get(self::HEADER));
            $reg = $products[sanitize_key($stylesheet)] ?? null;
            if ($pid === '' && $reg === null) {
                continue;
            }
            $items[] = [
                'slug'       => $stylesheet,
                'type'       => 'theme',
                'name'       => $theme->display('Name'),
                'version'    => (string) $theme->get('Version'),
                'product_id' => $pid !== '' ? $pid : (string) ($reg['product_id'] ?? ''),
                'state'      => $stylesheet === get_stylesheet() ? 'active' : 'installed',
                'registered' => $reg !== null,
                'licensed'   => $reg !== null ? $licensing->is_active($stylesheet) : null,
            ];
        }

        return $items;
    }
}
