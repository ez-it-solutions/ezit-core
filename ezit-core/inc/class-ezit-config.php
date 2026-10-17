<?php
/**
 * EzIT_Config — versioned bootstrap config from the license server.
 *
 * The site hardcodes only two facts: the bootstrap URL (EZIT_API_URL or
 * the ezit_api_url option) and each product's api_key. Everything else —
 * API base, endpoint map, service URLs, notices — comes from
 * /api/get_config, cached in a single option:
 *
 *   ezit_server_config = ['version' => int, 'fetched_at' => int, 'doc' => array]
 *
 * Sync triggers (cheapest first):
 *  1. maybe_sync($v) — every verify_license / check_update response
 *     carries `config_version`; a higher version forces a re-read.
 *  2. refresh_if_stale() — hourly-cron floor in case no call happens.
 *  3. On transport failure the fetch retries against the bootstrap URL,
 *     so a bad/moved api_base can never wedge the site.
 *
 * After a successful sync, EzIT_Core::api_url() resolves to the doc's
 * `api_base` — repointing every site is a one-click server-side publish.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Config
{
    public const OPTION   = 'ezit_server_config';
    private const MAX_AGE = 12 * HOUR_IN_SECONDS;

    // ── stored state ────────────────────────────────────────────────

    /** @return array{version:int,fetched_at:int,doc:array} empty array if never fetched */
    public static function get(): array
    {
        $cfg = get_option(self::OPTION, []);
        return is_array($cfg) && isset($cfg['doc']) ? $cfg : [];
    }

    public static function version(): int
    {
        return (int) (self::get()['version'] ?? 0);
    }

    public static function doc(): array
    {
        return self::get()['doc'] ?? [];
    }

    /**
     * Host base URL from the published config (e.g. the doc's
     * "https://ezops.ez-it-solutions.com/api/" → "...com"). Null if no
     * doc cached yet — callers fall back to the bootstrap URL.
     */
    public static function api_base(): ?string
    {
        $base = self::doc()['api_base'] ?? '';
        if (! is_string($base) || $base === '') {
            return null;
        }
        return preg_replace('#/api/?$#', '', trim($base)) ?: null;
    }

    /** Resolved endpoint path for a logical name, or null to use as-is. */
    public static function endpoint(string $name): ?string
    {
        $map = self::doc()['endpoints'] ?? [];
        $path = is_array($map) ? ($map[$name] ?? null) : null;
        return is_string($path) && $path !== '' ? $path : null;
    }

    /** Service URL (marketplace/support/billing…) or null. */
    public static function service(string $name): ?string
    {
        $services = self::doc()['services'] ?? [];
        $url = is_array($services) ? ($services[$name] ?? null) : null;
        return is_string($url) && $url !== '' ? $url : null;
    }

    /** Broadcast notices published in the config doc. */
    public static function notices(): array
    {
        $notices = self::doc()['notices'] ?? [];
        return is_array($notices) ? $notices : [];
    }

    // ── sync ────────────────────────────────────────────────────────

    /** Cheap hint from any API response — sync only on a newer version. */
    public static function maybe_sync(int $remoteVersion): void
    {
        if ($remoteVersion > self::version()) {
            self::sync();
        }
    }

    /** Hourly-cron floor: refresh if never fetched or past MAX_AGE. */
    public static function refresh_if_stale(): void
    {
        $cfg = self::get();
        if (! $cfg || (time() - (int) ($cfg['fetched_at'] ?? 0)) > self::MAX_AGE) {
            self::sync();
        }
    }

    /**
     * Fetch /api/get_config and store the doc. Returns true on a fresh
     * read; on transport failure retries once against the bootstrap URL
     * (handles the "api_base moved while old host is down" case).
     */
    public static function sync(): bool
    {
        $product = null;
        foreach (ezit_core()->products() as $p) {
            if (! empty($p['api_key'])) {
                $product = $p;
                break;
            }
        }
        if (! $product) {
            return false;
        }

        $res = EzIT_API::post('get_config', $product);

        // Transport failure → the effective api_base may be dead; retry
        // on the bootstrap host so the site can always come home.
        if (is_wp_error($res)) {
            $bootstrap = ezit_core()->bootstrap_url();
            if (ezit_core()->api_url() !== $bootstrap) {
                $res = EzIT_API::post('get_config', $product, [], $bootstrap);
            }
        }

        if (is_wp_error($res) || empty($res['status']) || empty($res['config']) || ! is_array($res['config'])) {
            return false;
        }

        update_option(self::OPTION, [
            'version'    => (int) ($res['config_version'] ?? 0),
            'fetched_at' => time(),
            'doc'        => $res['config'],
        ], false);

        /**
         * Fires after a new server config is applied — lets themes/other
         * modules react to api_base/service/notice changes.
         */
        do_action('ezit_config_synced', $res['config'], (int) ($res['config_version'] ?? 0));

        return true;
    }
}
