<?php
/**
 * EzIT_Gate — maintenance mode driven by license state.
 *
 * Ported from the standalone "EzIT License Gate" plugin
 * (registration/WordPress/Plugin/v1.0) — same behaviour, but reads
 * product/license state from EzIT_Core + EzIT_Licensing instead of its
 * own option blob:
 *
 * - Armed via Settings → Ez IT Core → "Site gate".
 * - Every product registered with `gated => true` (the default) and a
 *   product_id must hold a valid license: cert present, status not
 *   deactivated/invalid, domain + expiry sane locally. Remote truth is
 *   the daily verify cron — a failed check flips status to 'invalid'
 *   and closes the gate; an unreachable server keeps the last verdict.
 * - Logged-in editors/admins always pass through.
 * - Gate users (created on the license server: Licenses → Users) log in
 *   through the UC page's "Client access" form — POST /api/access_login
 *   returns an HMAC token stored as a 12 h host-bound cookie, verified
 *   locally (no per-request server call).
 * - Logout link: append ?ezit_gate_logout=1 to any URL.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Gate
{
    public const OPTION = 'ezit_gate';
    private const COOKIE = 'ezit_access';
    private const COOKIE_TTL = 12 * HOUR_IN_SECONDS;

    private EzIT_Core $core;
    private static ?self $instance = null;

    /** The booted instance — for templates that need gate state. */
    public static function instance(): ?self
    {
        return self::$instance;
    }

    public function __construct(EzIT_Core $core)
    {
        $this->core = $core;
        self::$instance = $this;

        add_action('init', [$this, 'handle_login']);
        add_action('init', [$this, 'handle_logout']);
        add_action('template_redirect', [$this, 'enforce'], 1);
    }

    public static function settings(): array
    {
        $s = wp_parse_args(get_option(self::OPTION, []), [
            'mode'       => '',   // off | licensed | maintenance (legacy: enabled)
            'enabled'    => 0,
            'layout'     => 'generic',
            'uc_title'   => __('Under Construction', 'ezit-core'),
            'uc_message' => __('We are working on something awesome. Please check back soon.', 'ezit-core'),
            'accent'     => '',   // blank = theme's uc defaults, else Ez IT green
            'bg'         => '',   // full-bleed background image URL
            'logo'       => '',   // logo image URL (blank = site-name wordmark)
            'countdown'  => '',   // ISO date — blank hides the timer
        ]);
        if ($s['mode'] === '') {
            $s['mode'] = ! empty($s['enabled']) ? 'licensed' : 'off';
        }
        return $s;
    }

    /**
     * Available UC layouts — file lives in templates/uc/{key}.php.
     * Filterable so a product/theme can ship its own.
     */
    public static function layouts(): array
    {
        return apply_filters('ezit_uc_layouts', [
            'generic' => __('Generic — theme-matched', 'ezit-core'),
            'maintenance' => __('Maintenance', 'ezit-core'),
            'minimal' => __('Minimal', 'ezit-core'),
            'hero'    => __('Hero', 'ezit-core'),
            'bold'    => __('Bold', 'ezit-core'),
            'split'   => __('Split', 'ezit-core'),
            'bright'  => __('Bright', 'ezit-core'),
            'glass'   => __('Glass', 'ezit-core'),
            'flat'    => __('Flat', 'ezit-core'),
        ]);
    }

    /**
     * Resolved visual identity for the UC page. Precedence:
     * Settings field > theme's register_product 'uc' args > Ez IT default.
     */
    private function brand(): array
    {
        $s = self::settings();
        $theme = [];
        $p = $this->gate_product();
        if ($p && ! empty($p['uc']) && is_array($p['uc'])) {
            $theme = $p['uc'];
        }
        return [
            'accent'    => $s['accent']    !== '' ? $s['accent']    : ($theme['accent']    ?? '#76b900'),
            'bg'        => $s['bg']        !== '' ? $s['bg']        : ($theme['bg']        ?? ''),
            'logo'      => $s['logo']      !== '' ? $s['logo']      : ($theme['logo']      ?? ''),
            'countdown' => $s['countdown'] !== '' ? $s['countdown'] : ($theme['countdown'] ?? ''),
        ];
    }

    // ── which products gate the site ────────────────────────────────

    /** Products that participate in the gate (gated flag + licensable). */
    public function gated_products(): array
    {
        return array_filter(
            $this->core->products(),
            static fn ($p) => ! empty($p['gated']) && ($p['product_id'] ?? '') !== ''
        );
    }

    /** The first gated product with an api_key — the one gate users
     *  authenticate under on the UC login form. */
    private function gate_product(): ?array
    {
        foreach ($this->gated_products() as $slug => $p) {
            if (($p['api_key'] ?? '') !== '') {
                $p['_slug'] = $slug;
                return $p;
            }
        }
        return null;
    }

    // ── visitor checks ──────────────────────────────────────────────

    /** Whether this request is allowed through to the site. */
    private function visitor_allowed(): bool
    {
        if (is_user_logged_in() && current_user_can('edit_posts')) {
            return true;
        }
        if ($this->valid_access_cookie()) {
            return true;
        }
        return $this->gate_licensed();
    }

    /**
     * License validity — local state only, no HTTP on the request path.
     * Every gated, licensable product must hold a sane, verified cert.
     */
    public function gate_licensed(): bool
    {
        $gated = $this->gated_products();
        if ($gated === []) {
            return true; // nothing registered/licensable → open
        }

        $licensing = $this->core->licensing();
        $host      = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        $ttl       = (int) apply_filters('ezit_gate_reverify_ttl', MINUTE_IN_SECONDS);

        // Synchronous re-verify of stale licenses — self-throttled by
        // checked_at, so only one request per window pays the remote call.
        // Covers BOTH directions: a license disabled on the server gates
        // the site on this visit; a re-enabled one reopens it.
        $licensing->freshen($ttl);

        foreach ($gated as $slug => $p) {
            $lic = $licensing->license($slug);
            if (! $lic || empty($lic['license_file'])) {
                return false;
            }
            if (! $licensing->is_active($slug)) {
                return false;
            }
            // Cheap local sanity — domain binding + expiry inside the cert.
            $payload = EzIT_Licensing::cert_payload($lic['license_file']);
            if ($payload) {
                if (! empty($payload['domain']) && $payload['domain'] !== $host) {
                    return false;
                }
                if (! empty($payload['expires_at']) && strtotime($payload['expires_at']) < time()) {
                    return false;
                }
            }
        }
        return true;
    }

    // ── access cookie (gate users) ──────────────────────────────────

    private function valid_access_cookie(): bool
    {
        $product = $this->gate_product();
        $token   = sanitize_text_field($_COOKIE[self::COOKIE] ?? '');
        if (! $product || $token === '') {
            return false;
        }

        $payload = self::parse_access_token($product['api_key'], $token);
        if (! $payload) {
            return false;
        }

        // Token is bound to this site's host.
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        return ! empty($payload['domain']) && $payload['domain'] === $host;
    }

    /**
     * Locally verify a gate-access token issued by /api/access_login.
     * Token: <hmac-hex>.<base64url(json)> keyed with "{api_key}|access".
     */
    public static function parse_access_token(string $api_key, string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$sig, $payload_b64] = $parts;

        $expected = hash_hmac('sha256', $payload_b64, $api_key . '|access');
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $json    = base64_decode(strtr($payload_b64, '-_', '+/'), true);
        $payload = $json ? json_decode($json, true) : null;
        if (! is_array($payload) || empty($payload['sub'])) {
            return null;
        }
        if (! empty($payload['exp']) && time() > (int) $payload['exp']) {
            return null;
        }

        return $payload;
    }

    // ── UC login / logout ───────────────────────────────────────────

    /** POST handler for the UC login form — runs before headers are sent. */
    public function handle_login(): void
    {
        if (is_admin() || empty($_POST['ezit_gate_login'])) {
            return;
        }

        $back    = wp_get_referer() ?: home_url('/');
        $product = $this->gate_product();

        if ($product) {
            $lic  = $this->core->licensing()->license($product['_slug']);
            $code = ($lic['license_code'] ?? '') !== ''
                ? $lic['license_code']
                : ($product['license_code'] ?? '');

            $res = EzIT_API::post('access_login', $product, [
                'product_id'   => $product['product_id'],
                'license_code' => $code,
                'username'     => sanitize_user(wp_unslash($_POST['ezit_gate_user'] ?? '')),
                'password'     => (string) wp_unslash($_POST['ezit_gate_pass'] ?? ''),
            ]);

            if (! is_wp_error($res) && ! empty($res['status']) && ! empty($res['token'])) {
                setcookie(self::COOKIE, $res['token'], [
                    'expires'  => time() + self::COOKIE_TTL,
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                wp_safe_redirect(remove_query_arg('ezit_gate_err', $back));
                exit;
            }

            $msg = is_wp_error($res)
                ? $res->get_error_message()
                : ($res['message'] ?? 'Login failed.');
        } else {
            $msg = __('Gate is not configured.', 'ezit-core');
        }

        wp_safe_redirect(add_query_arg('ezit_gate_err', rawurlencode($msg), $back));
        exit;
    }

    public function handle_logout(): void
    {
        if (empty($_GET['ezit_gate_logout'])) {
            return;
        }
        setcookie(self::COOKIE, '', time() - 3600, '/');
        wp_safe_redirect(remove_query_arg('ezit_gate_logout'));
        exit;
    }

    // ── enforcement ─────────────────────────────────────────────────

    public function enforce(): void
    {
        $s = self::settings();

        // Admin debug — ?ezit_gate_debug=1 dumps the gate decision state.
        if (isset($_GET['ezit_gate_debug']) && current_user_can('manage_options')) {
            $this->debug();
        }

        // Admin preview — ?ezit_uc_preview={layout} renders without 503.
        if (isset($_GET['ezit_uc_preview']) && current_user_can('manage_options')) {
            $this->render_uc(sanitize_key($_GET['ezit_uc_preview']), true);
        }

        if ($s['mode'] === 'off') {
            return;
        }
        // Armed gate: never let a page cache store guest HTML while the
        // license verdict can flip underneath it (filterable).
        if (apply_filters('ezit_gate_no_cache', true)) {
            if (! defined('DONOTCACHEPAGE')) {
                // WP Rocket, Super Cache, W3TC, LiteSpeed Cache…
                define('DONOTCACHEPAGE', true);
            }
            nocache_headers(); // no-store, must-revalidate — CDNs honoring origin headers
            header('X-LiteSpeed-Cache-Control: no-cache'); // server-level lscache
            header('X-Cache-Enabled: False');              // SiteGround/nginx-style hosts
        }
        if ($this->visitor_allowed()) {
            return;
        }
        // 'licensed' mode opens when every gated product is licensed;
        // 'maintenance' mode is unconditional (manual site maintenance).
        if ($s['mode'] === 'licensed') {
            if ($this->gate_licensed()) {
                return;
            }
            // Rescue check — the UC page is about to render. Force a
            // synchronous re-verify (15s floor protects the license server
            // from request storms while staying effectively instant) so a
            // license re-enabled server-side opens the site on THIS hit.
            $this->core->licensing()->freshen(
                (int) apply_filters('ezit_gate_rescue_ttl', 15)
            );
            if ($this->gate_licensed()) {
                return;
            }
        }

        status_header(503);
        nocache_headers();
        $this->render_uc($s['layout']);
    }

    /** Admin-only dump of everything that decides pass vs. gate. */
    private function debug(): void
    {
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        print_r($this->report()); // phpcs:ignore
        exit;
    }

    /** Gate decision state — shared by the debug endpoint + settings card. */
    public function report(): array
    {
        $s         = self::settings();
        $licensing = $this->core->licensing();
        $products  = [];
        foreach ($this->core->products() as $slug => $p) {
            $lic = $licensing->license($slug) ?: [];
            $products[$slug] = [
                'gated'        => ! empty($p['gated']),
                'product_id'   => $p['product_id'] !== '' ? 'set' : 'EMPTY',
                'has_cert'     => ! empty($lic['license_file']),
                'status'       => $lic['status'] ?? '(none)',
                'checked_at'   => isset($lic['checked_at'])
                    ? gmdate('Y-m-d H:i:s', $lic['checked_at']) . ' UTC'
                    : '(never)',
                'age_seconds'  => isset($lic['checked_at']) ? time() - $lic['checked_at'] : null,
                'is_active'    => $licensing->is_active($slug),
            ];
        }
        $report = [
            'mode'            => $s['mode'],
            'raw_option'      => array_diff_key(get_option(self::OPTION, []), ['uc_message' => 1]),
            'access_cookie'   => $this->valid_access_cookie(),
            'gate_licensed'   => $this->gate_licensed(),
            'guest_verdict'   => $this->valid_access_cookie() || $this->gate_licensed() ? 'PASS (site visible)' : 'GATED (UC page)',
            'products'        => $products,
            'push_url'        => rest_url('ezit/v1/license-push') . '  (POST key=<api_key>)',
            'cron'            => [
                'hourly_event' => wp_next_scheduled('ezit_daily_license_verify')
                    ? gmdate('Y-m-d H:i:s', wp_next_scheduled('ezit_daily_license_verify')) . ' UTC'
                    : '(not scheduled)',
                'ping_event'   => wp_next_scheduled('ezit_verify_ping_now')
                    ? gmdate('Y-m-d H:i:s', wp_next_scheduled('ezit_verify_ping_now')) . ' UTC'
                    : '(none)',
                'disabled'     => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            ],
        ];

        return $report;
    }

    /**
     * Theme stylesheets a UC page should pull in — the front-end css often
     * lives under assets/, not at style.css. Filter 'ezit_uc_stylesheets'
     * to override entirely.
     */
    private function theme_styles(): array
    {
        $dir   = get_stylesheet_directory();
        $uri   = get_stylesheet_directory_uri();
        $cands = ['style.css', 'assets/css/styles.css', 'assets/css/style.css', 'assets/css/main.css', 'css/styles.css'];
        $urls  = [];
        foreach ($cands as $rel) {
            $abs = "$dir/$rel";
            if (file_exists($abs)) {
                $urls[] = add_query_arg('ver', filemtime($abs), "$uri/$rel");
            }
        }
        return array_values((array) apply_filters('ezit_uc_stylesheets', $urls));
    }

    /** Render a UC layout template and stop the request. */
    private function render_uc(string $layout, bool $preview = false): void
    {
        // A UC page must never land in a page cache — a cached UC page
        // would keep serving "under construction" after re-enable, with
        // no PHP hit to run the rescue re-verify.
        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        $layouts = self::layouts();
        if (! isset($layouts[$layout])) {
            $layout = 'generic';
        }
        $file = EZIT_CORE_PATH . "templates/uc/{$layout}.php";
        if (! file_exists($file)) {
            $file = EZIT_CORE_PATH . 'templates/uc/generic.php';
        }

        $s         = self::settings();
        $title     = $preview && $s['uc_title'] === '' ? __('Under Construction', 'ezit-core') : $s['uc_title'];
        $message   = $s['uc_message'];
        $err       = isset($_GET['ezit_gate_err']) ? sanitize_text_field(wp_unslash($_GET['ezit_gate_err'])) : '';
        $site      = get_bloginfo('name');
        $brand     = $this->brand();
        $theme_css = $this->theme_styles();
        if ($preview && $brand['countdown'] === '') {
            $brand['countdown'] = gmdate('c', time() + 45 * DAY_IN_SECONDS); // sample timer
        }
        $post_to   = $preview ? add_query_arg('ezit_uc_preview', $layout, home_url('/')) : '';

        include $file;
        exit;
    }
}
