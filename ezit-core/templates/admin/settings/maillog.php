<?php
/**
 * Settings section — Email log: every wp_mail() the site attempted.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$entries = EzIT_Mail_Log::entries();
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
        <h3><?php esc_html_e('Email log', 'ezit-core'); ?></h3>
        <?php if ($entries !== []) : ?>
        <button type="submit" name="ezit_clear_maillog" value="1" class="ezit-meta"
                style="margin-left:auto;background:none;border:0;cursor:pointer;text-decoration:underline">
            <?php esc_html_e('Clear log', 'ezit-core'); ?>
        </button>
        <?php endif; ?>
    </div>
    <p class="ezit-meta" style="margin-bottom:16px">
        <?php esc_html_e('Every email the site tried to send — newest first. Failed entries carry the transport error.', 'ezit-core'); ?>
    </p>

    <?php if ($entries === []) : ?>
        <p class="ezit-meta"><?php esc_html_e('No email activity yet.', 'ezit-core'); ?></p>
    <?php else : ?>
    <div style="overflow-x:auto">
        <table class="widefat striped" style="border:0">
            <thead>
                <tr>
                    <th><?php esc_html_e('When', 'ezit-core'); ?></th>
                    <th><?php esc_html_e('Status', 'ezit-core'); ?></th>
                    <th><?php esc_html_e('To', 'ezit-core'); ?></th>
                    <th><?php esc_html_e('Subject', 'ezit-core'); ?></th>
                    <th><?php esc_html_e('Error', 'ezit-core'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $row) : $ok = ($row['status'] ?? '') === 'sent'; ?>
                <tr>
                    <td style="white-space:nowrap"><?php echo esc_html($row['time'] ?? ''); ?></td>
                    <td>
                        <span class="ezit-badge <?php echo $ok ? 'is-active' : 'is-inactive'; ?>">
                            <?php echo esc_html($ok ? __('Sent', 'ezit-core') : __('Failed', 'ezit-core')); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($row['to'] ?? ''); ?></td>
                    <td><?php echo esc_html($row['subject'] ?? ''); ?></td>
                    <td style="color:#b3483a;font-size:12.5px"><?php echo esc_html($row['error'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
