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

    /** Sender captured from PHPMailer at send time — some mailers strip
     *  the From header out of the list before wp_mail_succeeded fires. */
    private static ?string $pending_from = null;

    public static function boot(): void
    {
        add_action('phpmailer_init',    [self::class, 'capture_from']);
        add_action('wp_mail_succeeded', [self::class, 'sent']);
        add_action('wp_mail_failed',    [self::class, 'failed']);
    }

    /** phpmailer_init fires per wp_mail() — stash the mailer's From. */
    public static function capture_from($phpmailer): void
    {
        $from = trim((string) ($phpmailer->From ?? ''));
        $name = trim((string) ($phpmailer->FromName ?? ''));
        self::$pending_from = $from === ''
            ? null
            : ($name !== '' ? sprintf('%s <%s>', $name, $from) : $from);
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

        // Capture the From header for the inbox-style preview. Mailers
        // report headers inconsistently: a list of "From: x" strings,
        // one big header block, or a ['From' => x] map.
        $from = '';
        $headers = $data['headers'] ?? [];
        if (is_string($headers)) {
            $headers = explode("\n", $headers);
        }
        foreach ((array) $headers as $key => $h) {
            if (is_string($key) && strcasecmp($key, 'From') === 0) {
                $from = trim((string) $h); // assoc map
            } elseif (is_string($h) && stripos(ltrim($h), 'From:') === 0) {
                $from = trim(substr(ltrim($h), 5)); // "From: x" line
            }
        }
        if ($from === '' && self::$pending_from !== null) {
            $from = self::$pending_from;
        }
        self::$pending_from = null; // consumed — never leak into the next send

        array_unshift($log, [
            'time'    => time(), // epoch — display converts to site timezone
            'status'  => $status,
            'to'      => sanitize_text_field((string) $to),
            'subject' => sanitize_text_field((string) ($data['subject'] ?? '')),
            'error'   => sanitize_text_field($error),
            'from'    => sanitize_text_field($from),
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

    /** Full entry by index — preview endpoints need more than the body. */
    public static function entry(int $i): ?array
    {
        $row = self::entries()[$i] ?? null;
        if ($row === null) {
            return null;
        }
        $fmt = trim(get_option('date_format') . ' ' . get_option('time_format'));
        $ts  = $row['time'] ?? '';
        $row['time'] = is_numeric($ts) ? wp_date($fmt, (int) $ts) : (string) $ts;
        return $row;
    }

    public static function clear(): void
    {
        delete_option(self::OPTION);
    }
}

EzIT_Mail_Log::boot();
