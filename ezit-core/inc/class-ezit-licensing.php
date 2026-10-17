<?php
/**
 * EzIT_Licensing — activation state for every registered product.
 *
 * Stores the server's signed cert (`lic_response`) per product in the
 * `ezit_licenses` option and re-verifies remotely once a day. The cert's
 * HMAC can't be checked client-side (the server secret stays server-side)
 * — local checks only decode the JSON payload; remote verify is the truth.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Licensing
{
    public const OPTION = 'ezit_licenses';
    private const CRON  = 'ezit_daily_license_verify';
    private const PING  = 'ezit_verify_ping_now';

    private EzIT_Core $core;

    public function __construct(EzIT_Core $core)
    {
        $this->core = $core;

        // Hourly recurring verify (event predates the rename — reschedule
        // any old 'daily' occurrence in place).
        if (wp_get_schedule(self::CRON) !== 'hourly') {
            wp_clear_scheduled_hook(self::CRON);
            wp_schedule_event(time() + 300, 'hourly', self::CRON);
        }
        add_action(self::CRON, [$this, 'verify_all']);
        if (class_exists('EzIT_Config')) {
            add_action(self::CRON, [EzIT_Config::class, 'refresh_if_stale']);
        }

        // Freshness guarantees — three layers:
        // 1. admin_init: synchronous re-verify of stale licenses on every
        //    back-end request (throttled by checked_at, ~one call/window).
        // 2. init: async WP-Cron ping for the front-end (never blocks).
        // 3. hourly recurring verify as the floor.
        // The front-end gate additionally calls freshen() itself when armed.
        add_action('admin_init', [$this, 'freshen_admin']);
        add_action('init', [$this, 'maybe_ping']);
        add_action(self::PING, [$this, 'verify_all']);

        // Verdict flips detected in cron/REST can't always reach a cache
        // plugin's purge API — the flag makes the next front-end request
        // run the purge again in a context where it works.
        add_action('wp', [$this, 'consume_purge_flag']);

        // Products that opt in via register_product('deactivate_on_switch')
        // release their seat when their theme is switched away.
        add_action('switch_theme', [$this, 'on_switch_theme'], 10, 2);

        // Server → site push: the license server can POST
        // /wp-json/ezit/v1/license-push (key = a product api_key) to force
        // an immediate re-verify — zero propagation delay on changes.
        add_action('rest_api_init', function (): void {
            register_rest_route('ezit/v1', '/license-push', [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_push'],
                'permission_callback' => [$this, 'rest_push_auth'],
            ]);
        });
    }

    /** Push auth — license server presents a registered product api_key. */
    public function rest_push_auth(WP_REST_Request $r): bool
    {
        $key = (string) $r->get_param('key');
        if ($key === '') {
            return false;
        }
        foreach ($this->core->products() as $p) {
            if (($p['api_key'] ?? '') !== '' && hash_equals((string) $p['api_key'], $key)) {
                return true;
            }
        }
        return false;
    }

    /** Push handler — synchronous re-verify, returns fresh statuses. */
    public function rest_push(WP_REST_Request $r): WP_REST_Response
    {
        $this->verify_all();
        $out = [];
        foreach (array_keys($this->core->products()) as $slug) {
            $out[$slug] = $this->license($slug)['status'] ?? 'none';
        }
        return new WP_REST_Response(['ok' => true, 'statuses' => $out]);
    }

    /** Back-end freshness — synchronous, self-throttled via checked_at. */
    public function freshen_admin(): void
    {
        $this->freshen((int) apply_filters('ezit_verify_ping_ttl', MINUTE_IN_SECONDS));
    }

    /**
     * Synchronous re-verify of every product whose stored verdict is
     * stale. Call freely on the request path: checked_at is restamped on
     * every attempt (success OR transport error), so at most one remote
     * call per product happens inside a TTL window.
     */
    public function freshen(int $ttl = 120): void
    {
        foreach (array_keys($this->core->products()) as $slug) {
            $lic = $this->license($slug);
            if (! $lic || empty($lic['license_file'])) {
                continue;
            }
            if ((time() - (int) ($lic['checked_at'] ?? 0)) <= $ttl) {
                continue;
            }
            $this->verify($slug);
        }
    }

    /** Fire an async re-verify if the freshness window elapsed. */
    public function maybe_ping(): void
    {
        if (wp_doing_cron() || get_transient('ezit_verify_ping')) {
            return;
        }
        $ttl = (int) apply_filters('ezit_verify_ping_ttl', MINUTE_IN_SECONDS);
        set_transient('ezit_verify_ping', 1, $ttl);
        // Schedule even if one is already queued — a wedged cron event
        // shouldn't block future pings; the transient throttles anyway.
        wp_schedule_single_event(time(), self::PING);
        spawn_cron();
    }

    // ── stored state ────────────────────────────────────────────────

    private function all(): array
    {
        $data = get_option(self::OPTION, []);
        return is_array($data) ? $data : [];
    }

    public function license(string $slug): ?array
    {
        return $this->all()[sanitize_key($slug)] ?? null;
    }

    public function is_active(string $slug): bool
    {
        $lic = $this->license($slug);
        // 'invalid' = the server told us no (disabled/revoked) — not active.
        return ! empty($lic['license_file']) && ($lic['status'] ?? 'active') === 'active';
    }

    private function save(string $slug, array $lic): void
    {
        $data = $this->all();
        $data[sanitize_key($slug)] = $lic;
        update_option(self::OPTION, $data, false);
    }

    // ── remote calls ────────────────────────────────────────────────

    /** @return array{status:bool,message:string} */
    public function activate(string $slug, string $license_code, string $client_name = ''): array
    {
        $product = $this->core->product($slug);
        if (! $product || $product['product_id'] === '') {
            return ['status' => false, 'message' => 'Unknown or unconfigured product.'];
        }

        $res = EzIT_API::post('activate_license', $product, [
            'product_id'   => $product['product_id'],
            'license_code' => trim($license_code),
            'client_name'  => $client_name !== '' ? $client_name : parse_url(home_url(), PHP_URL_HOST),
            'verify_type'  => 'non_envato',
        ]);

        if (is_wp_error($res)) {
            return ['status' => false, 'message' => $res->get_error_message()];
        }

        if (! empty($res['status']) && ! empty($res['lic_response'])) {
            $this->save($slug, [
                'license_file' => $res['lic_response'],
                'license_code' => trim($license_code),
                'status'       => 'active',
                'checked_at'   => time(),
            ]);
        } else {
            // Failed activate carries the server's status_code too —
            // e.g. PRODUCT_DISABLED when the product was withdrawn.
            // Persist it so the UI shows the real reason, not a guess.
            $lic = $this->license($slug) ?: [];
            $lic['reason_code'] = (string) ($res['status_code'] ?? '');
            $lic['reason']      = (string) ($res['message'] ?? '');
            $lic['checked_at']  = time();
            $this->save($slug, $lic);
        }

        return [
            'status'  => ! empty($res['status']),
            'message' => $res['message'] ?? 'Activation failed.',
        ];
    }

    /** @return array{status:bool,message:string} */
    public function deactivate(string $slug): array
    {
        $product = $this->core->product($slug);
        $lic     = $this->license($slug);

        if (! $product || ! $lic || empty($lic['license_file'])) {
            return ['status' => false, 'message' => 'No active license to deactivate.'];
        }

        $res = EzIT_API::post('deactivate_license', $product, [
            'product_id'   => $product['product_id'],
            'license_file' => $lic['license_file'],
        ]);

        $ok = ! is_wp_error($res) && ! empty($res['status']);
        if ($ok || (is_wp_error($res) === false && isset($res['status_code']) && $res['status_code'] === 'LICENSE_NOT_FOUND')) {
            $lic['status'] = 'deactivated';
            unset($lic['license_file']);
            $this->save($slug, $lic);
        }

        return [
            'status'  => $ok,
            'message' => is_wp_error($res) ? $res->get_error_message()
                                           : ($res['message'] ?? 'Deactivation failed.'),
        ];
    }

    /** Remote verify of one product — returns true while still licensed. */
    public function verify(string $slug): bool
    {
        $product = $this->core->product($slug);
        $lic     = $this->license($slug);

        if (! $product || ! $lic || empty($lic['license_file'])) {
            return false;
        }

        $res = EzIT_API::post('verify_license', $product, [
            'product_id'   => $product['product_id'],
            'license_file' => $lic['license_file'],
        ]);

        if (is_wp_error($res)) {
            // Transport failure — keep the last verdict (grace), so a
            // flaky server can't revoke a working site. failing_since
            // stamps the START of the outage so admin can warn once the
            // grace window is exceeded.
            $lic['checked_at']    = time();
            $lic['failing_since'] = $lic['failing_since'] ?? time();
            $this->save($slug, $lic);
            return ($lic['status'] ?? '') === 'active';
        }

        // Config staleness hint rides every verify response — re-read
        // get_config when the published version moved.
        if (class_exists('EzIT_Config')) {
            EzIT_Config::maybe_sync((int) ($res['config_version'] ?? 0));
        }

        // Definitive answer — 'invalid' covers disabled/revoked server-side.
        $prev              = $lic['status'] ?? '';
        $ok                = ! empty($res['status']);
        $lic['status']     = $ok ? 'active' : 'invalid';
        unset($lic['failing_since']); // a real answer — any outage is over
        if ($ok) {
            unset($lic['reason_code'], $lic['reason']); // cleared block
        } else {
            // WHY it failed — status_code separates "product withdrawn"
            // from "activation disabled" from "license suspended".
            $lic['reason_code'] = (string) ($res['status_code'] ?? '');
            $lic['reason']      = (string) ($res['message'] ?? '');
        }
        // Sticky marker: active → invalid means disabled/revoked server-side
        // (vs. never activated). Cleared by a fresh activate().
        if ($prev === 'active' && ! $ok) {
            $lic['revoked'] = true;
        }
        $lic['checked_at'] = time();
        $this->save($slug, $lic);

        // A verdict flip must be visible to guests NOW — purge every page
        // cache we know about so the next request reaches PHP.
        if ($lic['status'] !== $prev) {
            $this->purge_caches();
        }

        return $ok;
    }

    /**
     * Verdict flipped — purge now AND flag a retry: if this ran in cron or
     * a REST push, a page-cache plugin's purge API may not be booted, so
     * the next front-end `wp` request consumes the flag and purges again.
     */
    private function purge_caches(): void
    {
        set_transient('ezit_purge_pending', 1, HOUR_IN_SECONDS);
        $this->do_purge();
    }

    /** Front-end retry — see purge_caches(). Runs on `wp`. */
    public function consume_purge_flag(): void
    {
        if (! get_transient('ezit_purge_pending')) {
            return;
        }
        delete_transient('ezit_purge_pending');
        $this->do_purge();
    }

    /** Purge every page/reverse-proxy cache we can reach. */
    private function do_purge(): void
    {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();                    // WP Rocket
        }
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();                   // WP Super Cache
        }
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();                         // W3 Total Cache
        }
        if (function_exists('wpo_cache_flush')) {
            wpo_cache_flush();                        // WP-Optimize
        }
        global $wp_fastest_cache;
        if (isset($wp_fastest_cache) && method_exists($wp_fastest_cache, 'deleteCache')) {
            $wp_fastest_cache->deleteCache(true);     // WP Fastest Cache
        }
        if (function_exists('sg_cachepress_purge_everything')) {
            sg_cachepress_purge_everything();         // SiteGround Optimizer
        }
        if (class_exists('Breeze_PurgeCache')) {
            Breeze_PurgeCache::execute_purge();       // Breeze (Cloudways)
        }
        global $nginx_purger;
        if (isset($nginx_purger) && method_exists($nginx_purger, 'purge_all')) {
            $nginx_purger->purge_all();               // Nginx Helper (rtCamp)
        }
        if (method_exists('WpeCommon', 'purge_varnish_cache')) {
            WpeCommon::purge_varnish_cache();         // WP Engine
        }
        global $kinsta_cache;
        if (isset($kinsta_cache->kinsta_cache_purge)
            && method_exists($kinsta_cache->kinsta_cache_purge, 'purge_complete_caches')) {
            $kinsta_cache->kinsta_cache_purge->purge_complete_caches(); // Kinsta
        }
        if (class_exists('VarnishPurger')) {          // DreamHost / ipstenu proxy purge
            $vp = method_exists('VarnishPurger', 'getInstance') ? VarnishPurger::getInstance() : null;
            if ($vp && method_exists($vp, 'executePurge')) {
                $vp->executePurge();
            }
        }

        do_action('litespeed_purge_all');             // LiteSpeed Cache

        // Generic reverse proxies: Varnish-style PURGE and LiteSpeed's
        // purge header, both fire-and-forget against the front page.
        foreach ([['method' => 'PURGE'], ['headers' => ['X-LiteSpeed-Purge' => '*']]] as $opts) {
            wp_remote_request(home_url('/'), array_merge($opts, [
                'timeout' => 2, 'sslverify' => false, 'blocking' => false,
            ]));
        }

        do_action('ezit_license_verdict_changed');    // host/CDN integrations
    }

    /** Hourly cron + ping events — re-verify everything registered. */
    public function verify_all(): void
    {
        foreach (array_keys($this->core->products()) as $slug) {
            if ($this->license($slug)) {
                $this->verify($slug);
            }
        }
    }

    /**
     * Products whose remote verify has been failing longer than the grace
     * window — returns slug => days failing, for the admin warning banner.
     */
    public function stale_products(): array
    {
        $days = max(1, (int) apply_filters('ezit_license_grace_days', 3));
        $out  = [];
        foreach ($this->all() as $slug => $lic) {
            $since = (int) ($lic['failing_since'] ?? 0);
            if ($since === 0 || empty($lic['license_file'])) {
                continue;
            }
            $down = (int) floor((time() - $since) / DAY_IN_SECONDS);
            if ($down >= $days) {
                $out[$slug] = $down;
            }
        }
        return $out;
    }

    /**
     * Theme switched away — release the license seat for products that
     * opted in (register_product 'deactivate_on_switch'). Best effort:
     * a transport failure leaves the license stored, never breaks the
     * switch.
     */
    public function on_switch_theme(string $new_name, $new_theme): void
    {
        $old = get_option('theme_switched'); // stylesheet dir of the theme just deactivated
        foreach ($this->core->products() as $slug => $p) {
            if (($p['type'] ?? 'theme') !== 'theme' || empty($p['deactivate_on_switch'])) {
                continue;
            }
            if ($slug === $old && $this->license($slug)) {
                try {
                    $this->deactivate($slug);
                } catch (\Throwable $e) { /* switching away must never fatal */ }
            }
        }
    }

    /**
     * Human label for a blocked product — driven by the server's
     * reason_code, falling back to its message, then a generic string.
     */
    public static function blocked_label(?array $lic): string
    {
        $code = (string) ($lic['reason_code'] ?? '');
        $map  = [
            'PRODUCT_DISABLED'    => __('This product is no longer available. Contact Ez IT Solutions.', 'ezit-core'),
            'ACTIVATION_DISABLED' => __('This activation was disabled on the license server. Enter a different license code to re-activate, or contact Ez IT Solutions.', 'ezit-core'),
            'LICENSE_SUSPENDED'   => __('This license has been suspended. Contact Ez IT Solutions.', 'ezit-core'),
            'LICENSE_EXPIRED'     => __('This license has expired. Contact Ez IT Solutions to renew.', 'ezit-core'),
            'LICENSE_INACTIVE'    => __('This license is not active. Contact Ez IT Solutions.', 'ezit-core'),
            'DOMAIN_NOT_ACTIVATED'=> __('This license is not activated for this domain. Re-enter your license code to activate.', 'ezit-core'),
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }
        $msg = trim((string) ($lic['reason'] ?? ''));
        return $msg !== '' ? $msg
            : __('This activation is not valid on the license server. Enter a different license code to re-activate, or contact Ez IT Solutions.', 'ezit-core');
    }

    // ── cert payload (informational only — HMAC is server-side) ─────

    /** Decode the base64url JSON tail of a stored cert for display. */
    public static function cert_payload(string $license_file): ?array
    {
        // format: <64 hex hmac><base64url(json)>
        $payload = substr($license_file, 64);
        if ($payload === false) {
            return null;
        }
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        $data = $json ? json_decode($json, true) : null;
        return is_array($data) ? $data : null;
    }
}
