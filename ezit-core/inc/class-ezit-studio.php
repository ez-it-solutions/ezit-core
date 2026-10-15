<?php
/**
 * EzIT_Studio — reads the `fields` schema products declare via
 * register_product() and turns it into editable Site Studio forms.
 * Values live in theme mods ("theme_mods_<stylesheet>"), the same storage
 * the Customizer writes — Studio and the Customizer stay in sync, and
 * edits survive theme updates.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Studio
{
    /**
     * Field schemas declared by registered products.
     *
     * @return array<string,array> slug => schema (with ['product'] name added)
     */
    public static function schemas(): array
    {
        $out = [];
        foreach (ezit_core()->products() as $slug => $p) {
            $schema = $p['fields'] ?? null;
            // Lazy schema: a callable name lets the theme defer building
            // the field map until render, after its helpers are loaded.
            if (is_string($schema) && is_callable($schema)) {
                $schema = $schema();
            }
            if (is_array($schema) && ! empty($schema['sections'])) {
                $schema['product'] = $p['name'] ?? $slug;
                $out[$slug] = $schema;
            }
        }
        return $out;
    }

    /** Every mod key the schema owns (for save whitelisting). */
    public static function field_keys(array $schema): array
    {
        $keys = [];
        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] ?? [] as $key => $field) {
                $keys[$key] = $field;
            }
        }
        return $keys;
    }

    /** Raw theme mods for a product's theme — active or not. */
    public static function mods(string $slug): array
    {
        $mods = $slug === get_stylesheet()
            ? get_theme_mods()
            : get_option('theme_mods_' . $slug, []);
        return is_array($mods) ? $mods : [];
    }

    /** Current value of one field: stored mod, else its declared default. */
    public static function value(string $slug, string $key, array $schema): string
    {
        $mods = self::mods($slug);
        $mod  = ($schema['mod_prefix'] ?? '') . $key;
        if (isset($mods[$mod]) && $mods[$mod] !== '') {
            return (string) $mods[$mod];
        }
        return (string) ($schema['defaults'][$key] ?? '');
    }

    /**
     * Persist the posted Studio fields for one product.
     * Only declared field keys are written; storage is the theme's own
     * mods option so inactive themes can be edited too.
     *
     * @param array $posted Raw $_POST values keyed by field id.
     */
    public static function save(string $slug, array $schema, array $posted): void
    {
        $prefix  = $schema['mod_prefix'] ?? '';
        $allowed = self::field_keys($schema);

        if ($slug === get_stylesheet()) {
            foreach ($allowed as $key => $field) {
                if (! array_key_exists($key, $posted)) {
                    continue;
                }
                set_theme_mod($prefix . $key, self::sanitize($field, $posted[$key]));
            }
            return;
        }

        $mods = self::mods($slug);
        foreach ($allowed as $key => $field) {
            if (array_key_exists($key, $posted)) {
                $mods[$prefix . $key] = self::sanitize($field, $posted[$key]);
            }
        }
        update_option('theme_mods_' . $slug, $mods);
    }

    private static function sanitize(array $field, $raw): string
    {
        $raw  = wp_unslash((string) $raw);
        $type = $field['type'] ?? 'text';
        return match ($type) {
            'textarea' => sanitize_textarea_field($raw),
            'url'      => esc_url_raw($raw),
            'email'    => sanitize_email($raw),
            default    => sanitize_text_field($raw),
        };
    }
}
