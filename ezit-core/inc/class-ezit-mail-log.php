<?php
/**
 * EzIT_Mail_Log — a lightweight mail log for every wp_mail() the site
 * sends. WP fires wp_mail_succeeded / wp_mail_failed around each send,
 * so this captures contact forms, license mail, WP system mail — all of
 * it — with recipient, subject, timestamp and the transport's error.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Mail_Log
{
    public const OPTION = 'ezit_mail_log';
    public const MAX    = 100;

    public static function boot(): void
    {
        add_action('wp_mail_succeeded', [self::class, 'sent']);
        add_action('wp_mail_failed', [self::class, 'failed']);
    }

    /** @param array $data mail args as sent: to, subject, headers… */
    public static function sent(array $data): void
    {
        self::record('sent', $data, '');
    }

    public static function failed(WP_Error $error): void
    {
        // WP stuffs the original mail args into the error data.
        $data = $error->get_error_data();
        self::record('failed', is_array($data) ? $data : [], $error->get_error_message());
    }

    private static function record(string $status, array $data, string $error): void
    {
        $to = $data['to'] ?? '';
        if (is_array($to)) {
            $to = implode(', ', $to);
        }

        $log = get_option(self::OPTION, []);
        if (! is_array($log)) {
            $log = [];
        }

        array_unshift($log, [
            'time'    => time(), // epoch — display converts to site timezone
            'status'  => $status,
            'to'      => sanitize_text_field((string) $to),
            'subject' => sanitize_text_field((string) ($data['subject'] ?? '')),
            'error'   => sanitize_text_field($error),
            'body'    => (string) ($data['message'] ?? ''),
        ]);

        update_option(self::OPTION, array_slice($log, 0, self::MAX), false);
    }

    /** @return array<int,array> newest first */
    public static function entries(): array
    {
        $log = get_option(self::OPTION, []);
        return is_array($log) ? $log : [];
    }

    /**
     * Entries with `time` rendered in the site's timezone using the
     * Settings → General date+time formats (no hardcoded 24h clock).
     */
    public static function formatted(): array
    {
        $fmt = trim(get_option('date_format') . ' ' . get_option('time_format'));
        return array_map(static function (array $row) use ($fmt): array {
            $ts = $row['time'] ?? '';
            // Epoch (new) vs legacy 'Y-m-d H:i:s' stored by the first build.
            $row['time'] = is_numeric($ts)
                ? wp_date($fmt, (int) $ts)
                : (string) $ts;
            $row['has_body'] = ($row['body'] ?? '') !== '';
            unset($row['body']); // list payloads stay light — view endpoint serves it
            return $row;
        }, self::entries());
    }

    /** Raw stored body for the preview endpoint. */
    public static function body(int $i): string
    {
        $log = self::entries();
        return (string) ($log[$i]['body'] ?? '');
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }
}

EzIT_Mail_Log::boot();
