<?php
/**
 * EzIT_API — thin client for the Ez IT license server.
 *
 * Speaks the LicenseBox protocol (identical to what license.ez-it-solutions.com
 * exposes): every request carries LB-API-KEY / LB-URL / LB-IP / LB-LANG
 * headers, endpoints live under /api/*.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_API
{
    /**
     * POST to /api/{endpoint} for a registered product.
     *
     * @return array|WP_Error decoded JSON body, or WP_Error on transport
     *         / HTTP failure.
     */
    public static function post(string $endpoint, array $product, array $body = [], ?string $base = null)
    {
        $core = ezit_core();

        // Endpoint map from the server config — lets the server rename
        // endpoints without a plugin deploy. Only bare names remap;
        // parameterized paths ('download_update/main/x') pass through.
        if (strpos($endpoint, '/') === false && class_exists('EzIT_Config')) {
            $endpoint = EzIT_Config::endpoint($endpoint) ?? $endpoint;
        }

        $base = $base !== null ? untrailingslashit($base) : $core->api_url();

        $response = wp_remote_post($base . '/api/' . ltrim($endpoint, '/'), [
            'timeout' => 20,
            'headers' => self::headers($product),
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if ($code >= 400) {
            // 503 + SERVER_UNAVAILABLE = the server's maintenance switch
            // is on — a distinct error code lets callers show "planned
            // outage" instead of "server down" and honor Retry-After.
            $err = new WP_Error(
                ($code === 503 && ($json['status_code'] ?? '') === 'SERVER_UNAVAILABLE')
                    ? 'ezit_server_unavailable'
                    : 'ezit_http_' . $code,
                $json['message'] ?? sprintf('License server returned HTTP %d', $code)
            );
            $err->add_data([
                'status_code' => (string) ($json['status_code'] ?? ''),
                'retry_after' => (int) wp_remote_retrieve_header($response, 'retry-after'),
                'http'        => $code,
            ]);
            return $err;
        }

        if ($json === null) {
            return new WP_Error('ezit_bad_json', 'License server returned an unreadable response.');
        }

        return $json;
    }

    /** HEAD request (update package sizes). Returns headers array. */
    public static function head(string $path, array $product)
    {
        $response = wp_remote_head(ezit_core()->api_url() . $path, [
            'timeout' => 15,
            'headers' => self::headers($product),
        ]);

        return is_wp_error($response) ? $response : wp_remote_retrieve_headers($response);
    }

    /**
     * Streaming download — used by the updater to pull a licensed zip.
     * Returns the temp file path or WP_Error.
     */
    public static function download(string $endpoint, array $product, array $body = [])
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $tmp = wp_tempnam('ezit-update.zip');
        if (! $tmp) {
            return new WP_Error('ezit_tmp', 'Could not create a temp file.');
        }

        $response = wp_remote_post(ezit_core()->api_url() . '/api/' . ltrim($endpoint, '/'), [
            'timeout'  => 300,
            'stream'   => true,
            'filename' => $tmp,
            'headers'  => self::headers($product),
            'body'     => $body,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
            @unlink($tmp);
            $code = is_wp_error($response) ? 'ezit_transport' : 'ezit_http_' . wp_remote_retrieve_response_code($response);
            return new WP_Error($code, 'Package download failed — is the license active?');
        }

        return $tmp;
    }

    /** LicenseBox headers — same contract the server enforces for Botble. */
    private static function headers(array $product): array
    {
        return [
            'LB-API-KEY' => $product['api_key'] ?? '',
            'LB-URL'     => home_url(),
            'LB-IP'      => $_SERVER['SERVER_ADDR'] ?? '',
            'LB-LANG'    => get_locale() ?: 'en',
        ];
    }
}
