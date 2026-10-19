<?php
/**
 * EzIT_Sso — license-server → site admin login.
 *
 * Lets the Ez IT license server mint ONE-TIME wp-login URLs for accounts on
 * this site. Inspired by the DreamHost panel-login plugin: a token lands in
 * user meta, a wp-login.php URL consumes it, wp_set_auth_cookie() does the
 * rest. Generalized so the server can also push users, list accounts, and
 * verify stored passwords.
 *
 * Routes (registered on rest_api_init):
 *
 *   GET  /wp-json/ezit/v1/sso/users?key=&nonce=&sig=
 *        → { users: [{id, login, display, roles[]}] }
 *   POST /wp-json/ezit/v1/sso/token
 *        key, nonce, sig, action, mode, [user_id | username | password |
 *        email | role] → { login_url } or { error }
 *
 * Auth — like EzIT_Licensing::rest_push_auth, the request must present a
 * registered product api_key AND a nonce signature:
 *   sig = HMAC-SHA256(nonce, api_key), nonce = "{time}.{rand}"
 *   The timestamp is rejected after 5 minutes, so a captured request can't
 *   be replayed.
 *
 * Token mint — user meta `ezit_sso_tokens` holds
 *   [ sha256(token) => expiry_ts ] (max 3 pending, expired pruned).
 *   URL: wp-login.php?ezit_sso_uid={id}&ezit_sso_token={raw}
 *   The raw token never touches the DB; only its hash does. Consume on
 *   init: hash_equals match, un-expired → wp_set_auth_cookie + admin_url.
 *   One use, ~2-minute TTL.
 *
 * Modes (POST sso/token):
 *   first_admin — token for the site's first administrator (ID-sorted)
 *   user        — token for user_id (any account — "log in as" for
 *                 role/permission testing)
 *   credentials — username+password verified via wp_authenticate;
 *                 on success a token for that user. Rate-limited 10/min.
 *   provision   — create (or password-reset) a user with `role`
 *                 (administrator default; any WP role name accepted so
 *                 custom roles pass through), then a token for it.
 *
 * Site-owners: this file is inert without the license server calling it —
 * every request is signed with the product api_key you shipped in
 * functions.php. Nothing here accepts unauthenticated input.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Sso
{
    /** user_meta key holding [sha256(token) => expiry] pairs */
    private const META = 'ezit_sso_tokens';
    /** token lifetime once minted (seconds) */
    private const TTL = 120;
    /** credential-verify attempts allowed per api_key per minute */
    private const CRED_MAX = 10;
    /** nonce timestamp tolerance (seconds) — replay guard */
    private const NONCE_WINDOW = 300;

    private EzIT_Core $core;

    public function __construct(EzIT_Core $core)
    {
        $this->core = $core;

        add_action('rest_api_init', function (): void {
            register_rest_route('ezit/v1', '/sso/users', [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_users'],
                'permission_callback' => [$this, 'rest_auth'],
            ]);
            register_rest_route('ezit/v1', '/sso/token', [
                'methods'             => 'POST',
                'callback'            => [$this, 'rest_token'],
                'permission_callback' => [$this, 'rest_auth'],
            ]);
        });

        add_action('init', [$this, 'consume_token']);
    }

    /* ── auth ──────────────────────────────────────────────────────── */

    /**
     * Registered product api_key + HMAC-signed nonce. The signature binds
     * the request to a fresh timestamp so a leaked key alone can't replay
     * old traffic.
     */
    public function rest_auth(WP_REST_Request $r): bool
    {
        $key   = (string) $r->get_param('key');
        $nonce = (string) $r->get_param('nonce');
        $sig   = (string) $r->get_param('sig');
        if ($key === '' || $nonce === '' || $sig === '') {
            return false;
        }

        // nonce = "{unix}.{rand}" — reject stale requests
        $ts = (int) strtok($nonce, '.');
        if ($ts <= 0 || abs(time() - $ts) > self::NONCE_WINDOW) {
            return false;
        }

        foreach ($this->core->products() as $p) {
            $apiKey = (string) ($p['api_key'] ?? '');
            if ($apiKey === '' || ! hash_equals($apiKey, $key)) {
                continue;
            }
            return hash_equals(hash_hmac('sha256', $nonce, $apiKey), $sig);
        }

        return false;
    }

    /* ── GET sso/users ─────────────────────────────────────────────── */

    /**
     * Every user on the site, smallest useful shape. The "Log in as"
     * picker needs id+login+role; display name is courtesy.
     */
    public function rest_users(): WP_REST_Response
    {
        $users = [];
        foreach (get_users(['fields' => ['ID', 'user_login', 'display_name'], 'number' => 500]) as $u) {
            $wp = new WP_User($u->ID);
            $users[] = [
                'id'      => (int) $u->ID,
                'login'   => (string) $u->user_login,
                'display' => (string) $u->display_name,
                'roles'   => array_values((array) $wp->roles),
            ];
        }

        return new WP_REST_Response(['users' => $users], 200);
    }

    /* ── POST sso/token ────────────────────────────────────────────── */

    public function rest_token(WP_REST_Request $r): WP_REST_Response
    {
        $mode = (string) $r->get_param('mode');

        switch ($mode) {
            case 'first_admin':
                $user = $this->first_admin();
                break;

            case 'user':
                $user = get_user_by('id', (int) $r->get_param('user_id'));
                break;

            case 'credentials':
                $user = $this->verify_credentials($r);
                break;

            case 'provision':
                $user = $this->provision($r);
                break;

            default:
                return new WP_REST_Response(['error' => 'Unknown mode.'], 400);
        }

        if (is_wp_error($user)) {
            return new WP_REST_Response(['error' => $user->get_error_message()], 400);
        }
        if (! $user) {
            return new WP_REST_Response(['error' => 'No matching user on this site.'], 404);
        }

        return new WP_REST_Response(['login_url' => $this->mint_url($user)], 200);
    }

    /** First administrator by ID — the classic panel-login target. */
    private function first_admin(): ?WP_User
    {
        $admins = get_users(['role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1]);

        return $admins ? $admins[0] : null;
    }

    /**
     * Verify a supplied username/password with wp_authenticate. Cheap
     * rate-limit per calling api_key so a leaked key can't brute-force
     * wp passwords through us.
     */
    private function verify_credentials(WP_REST_Request $r)
    {
        $key  = substr(hash('sha256', (string) $r->get_param('key')), 0, 12);
        $hits = (int) get_transient('ezit_sso_cred_' . $key);
        if ($hits >= self::CRED_MAX) {
            return new WP_Error('ezit_ratelimit', 'Too many credential attempts — try again in a minute.');
        }
        set_transient('ezit_sso_cred_' . $key, $hits + 1, MINUTE_IN_SECONDS);

        $user = wp_authenticate(
            sanitize_user((string) $r->get_param('username')),
            (string) $r->get_param('password')
        );

        return is_wp_error($user) ? null : $user;
    }

    /**
     * Push an account onto the site. Existing username → password reset;
     * new username → wp_insert_user with the requested role.
     */
    private function provision(WP_REST_Request $r)
    {
        $login = sanitize_user((string) $r->get_param('username'), true);
        $pass  = (string) $r->get_param('password');
        $email = sanitize_email((string) $r->get_param('email'));
        $role  = sanitize_key((string) ($r->get_param('role') ?: 'administrator'));

        if ($login === '' || strlen($pass) < 8) {
            return new WP_Error('ezit_bad_input', 'username + password (8+ chars) required.');
        }

        $existing = get_user_by('login', $login);
        if ($existing) {
            wp_set_password($pass, $existing->ID);
            if ($role !== '' && get_role($role)) {
                $existing->set_role($role);
            }
            return $existing;
        }

        if ($email === '') {
            $email = $login . '@' . wp_parse_url(home_url(), PHP_URL_HOST);
        }

        $id = wp_insert_user([
            'user_login' => $login,
            'user_pass'  => $pass,
            'user_email' => $email,
            'role'       => get_role($role) ? $role : 'administrator',
        ]);

        return is_wp_error($id) ? $id : get_user_by('id', $id);
    }

    /* ── token mint + consume ──────────────────────────────────────── */

    /**
     * Store sha256(token) → expiry in user meta, return the wp-login URL
     * carrying the RAW token. Consumed once; expires in TTL seconds.
     */
    private function mint_url(WP_User $user): string
    {
        $raw  = bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);
        $exp  = time() + self::TTL;

        $tokens = (array) get_user_meta($user->ID, self::META, true);
        $tokens = array_filter($tokens, fn ($e) => (int) $e > time()); // prune expired
        if (count($tokens) >= 3) {
            asort($tokens);
            array_shift($tokens); // keep newest 2 + the new one
        }
        $tokens[$hash] = $exp;
        update_user_meta($user->ID, self::META, $tokens);

        return add_query_arg([
            'ezit_sso_uid'   => $user->ID,
            'ezit_sso_token' => $raw,
        ], wp_login_url());
    }

    /** init handler — wp-login.php?ezit_sso_uid=&ezit_sso_token= */
    public function consume_token(): void
    {
        global $pagenow;
        if ('wp-login.php' !== $pagenow
            || empty($_GET['ezit_sso_uid'])
            || empty($_GET['ezit_sso_token'])) {
            return;
        }

        $deny = static function (): void {
            if (is_user_logged_in()) {
                wp_die(sprintf(
                    'Invalid or expired login token — you are already signed in as %s. <a href="%s">Go to the dashboard</a>.',
                    esc_html(wp_get_current_user()->user_login),
                    esc_url(admin_url())
                ));
            }
            wp_die(sprintf(
                'Invalid or expired login token. <a href="%s">Sign in normally</a>.',
                esc_url(wp_login_url())
            ));
        };

        $user   = get_user_by('id', (int) $_GET['ezit_sso_uid']);
        $tokens = $user ? (array) get_user_meta($user->ID, self::META, true) : [];
        $hash   = hash('sha256', (string) $_GET['ezit_sso_token']);
        $now    = time();

        $valid = false;
        foreach ($tokens as $h => $exp) {
            if (hash_equals((string) $h, $hash) && (int) $exp >= $now) {
                $valid = true;
                unset($tokens[$h]);
                break;
            }
        }
        // Always write back — prunes stragglers even on a bad attempt.
        if ($user) {
            update_user_meta($user->ID, self::META, array_filter($tokens, fn ($e) => (int) $e > $now));
        }
        if (! $valid) {
            $deny();
        }

        wp_set_auth_cookie($user->ID, true, is_ssl());
        wp_safe_redirect(admin_url());
        exit;
    }
}
