<?php
/**
 * EzIT_Banners — client-facing announcements from the Ez IT server.
 *
 * POSTs /api/announcements once per registered product (LicenseBox
 * headers, same as licensing), caches the list in a transient for 6h,
 * and renders it as a dismissible admin banner. If the endpoint isn't
 * deployed yet the WP_Error just yields an empty list — nothing shows,
 * nothing breaks.
 *
 * Banner shape: { id, title, message, type: info|warning|success, url }
 * Local code can inject one without the server via:
 *   add_filter('ezit_banners', fn($b) => [...$b, ['id'=>..., ...]]);
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Banners
{
    private const TRANSIENT = 'ezit_banners_v1';
    private const TTL       = 6 * HOUR_IN_SECONDS;
    private const USER_META = 'ezit_dismissed_banners';

    private EzIT_Core $core;

    public function __construct(EzIT_Core $core)
    {
        $this->core = $core;
        add_action('admin_init', [$this, 'refresh']);
        add_action('admin_notices', [$this, 'render']);
        add_action('admin_post_ezit_dismiss_banner', [$this, 'handle_dismiss']);
    }

    /** Refill the cache when it expires. Cheap no-op when fresh. */
    public function refresh(): void
    {
        if (get_transient(self::TRANSIENT) !== false) {
            return;
        }
        $banners = [];
        foreach ($this->core->products() as $p) {
            if (($p['product_id'] ?? '') === '') {
                continue;
            }
            $res = EzIT_API::post('announcements', $p);
            if (is_wp_error($res) || empty($res['banners']) || ! is_array($res['banners'])) {
                continue;
            }
            foreach ($res['banners'] as $b) {
                if (! empty($b['id']) && ! empty($b['message'])) {
                    $banners[$b['id']] = $b;
                }
            }
        }
        set_transient(self::TRANSIENT, $banners, self::TTL);
    }

    /** Active banners minus ones this user dismissed. */
    private function active(): array
    {
        $cached = get_transient(self::TRANSIENT);
        $list   = is_array($cached) ? $cached : [];
        $list   = apply_filters('ezit_banners', $list);

        $dismissed = (array) get_user_meta(get_current_user_id(), self::USER_META, true);
        return array_filter($list, static fn ($b) => ! in_array($b['id'], $dismissed, true));
    }

    public function render(): void
    {
        $banners = $this->active();
        if ($banners === []) {
            return;
        }
        static $once = false;
        if (! $once) {
            $once = true;
            echo '<style>'
                . '.ezit-banner{display:flex;gap:14px;align-items:flex-start;border-left-width:4px;padding:14px 16px}'
                . '.ezit-banner h4{margin:0 0 4px;font-size:14px}'
                . '.ezit-banner p{margin:0;font-size:13px}'
                . '.ezit-banner a{white-space:nowrap}'
                . '.ezit-banner .ezit-banner__x{margin-left:auto;text-decoration:none;font-size:18px;line-height:1}'
                . '</style>';
        }
        foreach ($banners as $b) {
            $type  = in_array($b['type'] ?? '', ['warning', 'success', 'error'], true) ? $b['type'] : 'info';
            $class = 'notice ezit-banner notice-' . $type;
            $dismiss = wp_nonce_url(
                admin_url('admin-post.php?action=ezit_dismiss_banner&banner=' . rawurlencode($b['id'])),
                'ezit_dismiss_' . $b['id']
            );
            printf(
                '<div class="%s"><div><h4>%s</h4><p>%s</p></div>%s<a class="ezit-banner__x" href="%s" aria-label="%s">&times;</a></div>',
                esc_attr($class),
                esc_html($b['title'] ?? __('Ez IT announcement', 'ezit-core')),
                wp_kses_post($b['message']),
                ! empty($b['url'])
                    ? '<a class="button button-small" href="' . esc_url($b['url']) . '" target="_blank" rel="noopener">' . esc_html__('Details', 'ezit-core') . ' ↗</a>'
                    : '',
                esc_url($dismiss),
                esc_attr__('Dismiss', 'ezit-core')
            );
        }
    }

    /** Per-user dismissal — the banner stays down even after the cache refreshes. */
    public function handle_dismiss(): void
    {
        $id = sanitize_key(wp_unslash($_GET['banner'] ?? ''));
        check_admin_referer('ezit_dismiss_' . $id);
        if ($id !== '') {
            $list   = (array) get_user_meta(get_current_user_id(), self::USER_META, true);
            $list[] = $id;
            update_user_meta(get_current_user_id(), self::USER_META, array_values(array_unique($list)));
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url());
        exit;
    }
}
