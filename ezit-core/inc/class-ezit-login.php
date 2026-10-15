<?php
/**
 * EzIT_Login — branded wp-login.php.
 *
 * Controlled from Ez IT → Settings → Login page and stored in the
 * `ezit_login` option so the look survives theme switches:
 *   logo    — image URL replacing the WP logo
 *   accent  — button/link color (default: Ez IT green)
 *   bg      — page background color (default: Ez IT dark)
 *   message — optional text above the form (e.g. client-facing note)
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Login
{
    public const OPTION = 'ezit_login';

    public static function settings(): array
    {
        $s = get_option(self::OPTION, []);
        return array_merge([
            'logo'      => '',
            'accent'    => '',
            'bg_color'  => '',
            'bg_image'  => '',
            'message'   => '',
        ], is_array($s) ? $s : []);
    }

    /**
     * Product-supplied branding (the theme's register_product() uc array):
     * accent color, background image, logo URL.
     */
    public static function product_defaults(): array
    {
        $d = ['accent' => '', 'bg_image' => '', 'logo' => ''];
        if (! function_exists('ezit_core')) {
            return $d;
        }
        foreach (ezit_core()->products() as $p) {
            if (($p['type'] ?? '') !== 'theme') {
                continue;
            }
            $uc = $p['uc'] ?? [];
            $d['accent']   = $uc['accent'] ?? '';
            $d['bg_image'] = $uc['bg'] ?? '';
            $d['logo']     = $uc['logo'] ?? '';
            break;
        }
        // Convention fallback: the theme's own logo asset.
        if ($d['logo'] === '') {
            foreach (['assets/images/logo.svg', 'assets/images/logo.png', 'assets/images/logo.jpg', 'assets/img/logo.svg', 'assets/img/logo.png'] as $rel) {
                if (file_exists(get_template_directory() . '/' . $rel)) {
                    $d['logo'] = get_template_directory_uri() . '/' . $rel;
                    break;
                }
            }
        }
        return $d;
    }

    /** Resolved logo URL — explicit setting → WP custom logo → product → convention. */
    public static function logo_url(): string
    {
        $s = self::settings();
        if ($s['logo'] !== '') {
            return esc_url_raw($s['logo']);
        }
        $custom = get_theme_mod('custom_logo');
        if ($custom && ($src = wp_get_attachment_image_url((int) $custom, 'full'))) {
            return $src;
        }
        return self::product_defaults()['logo'];
    }

    public function __construct()
    {
        add_action('login_enqueue_scripts', [$this, 'styles']);
        add_filter('login_headerurl', [$this, 'header_url']);
        add_filter('login_headertext', [$this, 'header_text']);
        add_filter('login_message', [$this, 'message']);
    }

    /** Logo click goes home; alt text uses the site name. */
    public function header_url(): string
    {
        return home_url();
    }

    public function header_text(): string
    {
        return get_bloginfo('name');
    }

    /** Optional note rendered above the form. */
    public function message(string $m): string
    {
        $s = self::settings();
        if ($s['message'] === '') {
            return $m;
        }
        return '<p class="ezit-login-msg">' . esc_html($s['message']) . '</p>' . $m;
    }

    /** Inject the branded styles on wp-login.php only. */
    public function styles(): void
    {
        $s        = self::settings();
        $defaults = self::product_defaults();
        $accent   = sanitize_hex_color($s['accent']) ?: (sanitize_hex_color($defaults['accent']) ?: '#76b900');
        if (strlen($accent) === 4) { // 3-digit → 6-digit so alpha suffixes stay valid
            $accent = sprintf('#%s%s%s%s%s%s', $accent[1], $accent[1], $accent[2], $accent[2], $accent[3], $accent[3]);
        }
        $bg_color = sanitize_hex_color($s['bg_color']) ?: '#191919';
        $bg_image = $s['bg_image'] !== '' ? esc_url_raw($s['bg_image']) : esc_url_raw($defaults['bg_image']);
        $logo     = self::logo_url();

        $bg_css = "background: {$bg_color};";
        if ($bg_image !== '') {
            $bg_css = "background: {$bg_color} url('{$bg_image}') center/cover no-repeat fixed;";
        }
        $scrim = $bg_image !== ''
            ? 'body.login::before { content:""; position:fixed; inset:0; z-index:0;'
              . ' background:linear-gradient(160deg,rgba(25,25,25,.55),rgba(25,25,25,.78)); }'
              . ' #login { position:relative; z-index:1; }'
            : '';

        $check = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M14.83 4.89l1.34.94-5.81 8.38H9.02L5.78 9.67l1.34-1.25 2.57 2.4z" fill="' . $accent . '"/></svg>');

        $css = "
            body.login { {$bg_css} font-family:'Segoe UI',system-ui,-apple-system,Roboto,'Helvetica Neue',Arial,sans-serif; }
            {$scrim}
            #login { width:min(420px,92vw); padding-top:6vh; }
            body.login .ezit-login-msg {
                margin:0 auto 18px; padding:12px 16px; max-width:380px;
                background:rgba(255,255,255,.92); border-left:3px solid {$accent};
                border-radius:8px; text-align:center; color:#232323; font-size:14px;
            }
            .login h1 a { width:280px; height:120px; background-size:contain; background-position:center; }
            .login #loginform, .login #lostpasswordform, .login #registerform {
                margin-top:6px; padding:30px 28px 26px; border:none; border-radius:16px;
                box-shadow:0 24px 70px rgba(0,0,0,.45); position:relative; overflow:hidden;
            }
            .login #loginform::before, .login #lostpasswordform::before, .login #registerform::before {
                content:''; position:absolute; top:0; left:0; right:0; height:4px; background:{$accent};
            }
            .login label { color:#3c434a; font-weight:600; }
            .login input[type=text], .login input[type=password], .login input[type=email] {
                margin-top:6px; padding:9px 12px; font-size:15px; line-height:1.4;
                border:1.5px solid #d9dcd2; border-radius:9px; box-shadow:none; min-height:auto;
            }
            .login input[type=text]:focus, .login input[type=password]:focus,
            .login input[type=email]:focus, .login input[type=checkbox]:focus {
                border-color:{$accent}; box-shadow:0 0 0 3px {$accent}33; outline:none;
            }
            .login input[type=checkbox]:checked::before { content:url(\"data:image/svg+xml,{$check}\") !important; }
            .login .button.wp-hide-pw { color:#5c5f6b; }
            .login .button-primary {
                display:block; float:none; width:100%; height:46px; margin:14px 0 0;
                background:{$accent} !important; border-color:{$accent} !important;
                color:#fff !important; font-size:15px; font-weight:700; letter-spacing:.01em;
                border-radius:9px; text-shadow:none; box-shadow:0 6px 18px {$accent}55;
                transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
            }
            .login .button-primary:hover, .login .button-primary:focus {
                filter:brightness(1.1); transform:translateY(-1px); box-shadow:0 10px 24px {$accent}66;
            }
            .login .message, .login .notice, .login .success, .login .error {
                border-radius:9px; border-left-color:{$accent};
                box-shadow:0 8px 24px rgba(0,0,0,.25);
            }
            .login .error { border-left-color:#d63638; }
            .login #nav, .login #backtoblog { text-align:center; }
            .login #nav a, .login #backtoblog a { color:rgba(255,255,255,.85); transition:color .15s ease; }
            .login #nav a:hover, .login #backtoblog a:hover { color:#fff; }
            .login .language-switcher select { border-radius:8px; }
        ";
        if ($logo !== '') {
            $css .= ".login h1 a { background-image:url('{$logo}'); }";
        } else {
            // Ez IT bolt as the default mark on the branded screen.
            $bolt = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M13.4 2 4.6 13.4h6.2L9.4 22 19.4 9.6h-6.2z" fill="' . $accent . '"/></svg>');
            $css .= ".login h1 a { background-image:url(\"data:image/svg+xml,{$bolt}\"); width:120px; }";
        }
        wp_add_inline_style('login', $css);
    }
}
