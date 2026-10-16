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
            'time'    => current_time('mysql'),
            'status'  => $status,
            'to'      => sanitize_text_field((string) $to),
            'subject' => sanitize_text_field((string) ($data['subject'] ?? '')),
            'error'   => sanitize_text_field($error),
        ]);

        update_option(self::OPTION, array_slice($log, 0, self::MAX), false);
    }

    /** @return array<int,array> newest first */
    public static function entries(): array
    {
        $log = get_option(self::OPTION, []);
        return is_array($log) ? $log : [];
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }
}

EzIT_Mail_Log::boot();
