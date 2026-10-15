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
            'logo'    => '',
            'accent'  => '#76b900',
            'bg'      => '#191919',
            'message' => '',
        ], is_array($s) ? $s : []);
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
        $s      = self::settings();
        $accent = sanitize_hex_color($s['accent']) ?: '#76b900';
        $bg     = sanitize_hex_color($s['bg']) ?: '#191919';
        $logo   = $s['logo'] !== '' ? esc_url_raw($s['logo']) : '';
        // Readable tint for text on the dark background.
        $on_bg  = '#f0f0f1';

        $css = "
            body.login { background: {$bg}; }
            body.login a { color: {$accent}; }
            body.login .ezit-login-msg {
                padding: 0 0 16px; margin: 0 auto; max-width: 320px;
                text-align: center; color: {$on_bg}; font-size: 14px;
            }
            .login #loginform, .login #lostpasswordform, .login #registerform {
                border-color: {$accent};
                border-top-width: 3px;
                box-shadow: 0 8px 32px rgba(0,0,0,.35);
            }
            .login .button-primary {
                background: {$accent} !important; border-color: {$accent} !important;
                color: #191919 !important; font-weight: 700;
                text-shadow: none; box-shadow: none;
            }
            .login .button-primary:hover, .login .button-primary:focus {
                filter: brightness(1.12);
            }
            .login input[type=text]:focus, .login input[type=password]:focus,
            .login input[type=checkbox]:focus {
                border-color: {$accent};
                box-shadow: 0 0 0 1px {$accent};
            }
            .login input[type=checkbox]:checked::before { content: url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'><path d='M14.83 4.89l1.34.94-5.81 8.38H9.02L5.78 9.67l1.34-1.25 2.57 2.4z' fill='{$accent}'/></svg>\") !important; }
            .login #backtoblog a, .login #nav a { color: {$accent}; }
        ";
        if ($logo !== '') {
            $css .= "
                .login h1 a {
                    background-image: url('{$logo}');
                    background-size: contain; background-position: center;
                    width: 320px; height: 84px;
                }
            ";
        } else {
            // Ez IT bolt as the default mark on the branded screen.
            $bolt = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M13.4 2 4.6 13.4h6.2L9.4 22 19.4 9.6h-6.2z" fill="#76b900"/></svg>');
            $css .= "
                .login h1 a {
                    background-image: url(\"data:image/svg+xml,{$bolt}\");
                    background-size: contain; background-position: center;
                    width: 320px; height: 84px;
                }
            ";
        }
        wp_add_inline_style('login', $css);
    }
}
