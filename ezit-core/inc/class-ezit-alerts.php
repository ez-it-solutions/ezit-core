<?php
/**
 * EzIT_Alerts — theme-declared banner slots, core-managed content.
 *
 * Phase 8 pattern: theme declares, core renders config, WP stores.
 * A product registers named slots in register_product() (either top-level
 * 'slots' or inside 'uc'):
 *
 *   'slots' => [
 *       'enroll' => [
 *           'label'    => 'Enrollment notice bar',
 *           'defaults' => ['label'=>…, 'detail'=>…, 'cta'=>…, 'url'=>…],
 *       ],
 *   ],
 *
 * Core stores per-slot config in the ezit_slots option and themes read it
 * with ezit_slot('enroll') — which returns null when the slot is off or
 * outside its schedule window, so the theme simply doesn't render it.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Alerts
{
    public const OPTION = 'ezit_slots';

    /**
     * Every slot declared by registered products.
     * [ slot_key => ['label', 'defaults', 'product'] ]
     */
    public static function declared_slots(): array
    {
        $out = [];
        foreach (ezit_core()->products() as $slug => $p) {
            $slots = $p['slots'] ?? ($p['uc']['slots'] ?? []);
            foreach ((array) $slots as $key => $d) {
                $d              = is_array($d) ? $d : ['label' => (string) $d];
                $d['product']   = $p['name'] ?? $slug;
                $out[$key]      = $d;
            }
        }
        return $out;
    }

    /** Raw saved config bag. */
    public static function saved(): array
    {
        $s = get_option(self::OPTION, []);
        return is_array($s) ? $s : [];
    }

    /**
     * Resolved config for a slot — null when the theme should hide it.
     * Fields: on, mode (structured|custom), label, detail, cta, url,
     * custom (HTML), bg, color, start, end (all strings/ints).
     */
    public static function slot(string $key): ?array
    {
        $declared = self::declared_slots()[$key] ?? null;
        if ($declared === null) {
            return null; // nobody registered this slot
        }
        $saved = self::saved();
        $cfg   = array_merge($declared['defaults'] ?? [], $saved[$key] ?? []);

        // Never-saved slots default to ON (the theme shipped showing them);
        // once saved through Settings → Alerts, the checkbox rules.
        if (isset($saved[$key]) && empty($saved[$key]['on'])) {
            return null;
        }
        $now = current_time('timestamp'); // phpcs:ignore
        if (! empty($cfg['start']) && strtotime((string) $cfg['start']) > $now) {
            return null; // scheduled, not yet live
        }
        if (! empty($cfg['end']) && strtotime((string) $cfg['end']) < $now) {
            return null; // window closed
        }
        return $cfg;
    }
}

/**
 * Theme helper — returns the slot config array or null when hidden.
 * Themes: `if ($cfg = ezit_slot('enroll')) { render bar with $cfg }`.
 */
function ezit_slot(string $key): ?array
{
    return EzIT_Alerts::slot($key);
}
