<?php
/**
 * Settings section — Email log: every wp_mail() the site attempted.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$all      = EzIT_Mail_Log::formatted();
$per_page = 20;
$total    = count($all);
$pages    = max(1, (int) ceil($total / $per_page));
$mlog_pg  = min($pages, max(1, (int) ($_GET['mlog_page'] ?? 1))); // phpcs:ignore
$entries  = array_slice($all, ($mlog_pg - 1) * $per_page, $per_page);

$nav = '';
if ($pages > 1) {
    $nav = paginate_links([
        'base'      => add_query_arg('mlog_page', '%#%'),
        'format'    => '',
        'current'   => $mlog_pg,
        'total'     => $pages,
        'prev_text' => '&larr;',
        'next_text' => '&rarr;',
        'type'      => 'list',
        'add_args'  => false,
    ]);
}
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
        <h3><?php esc_html_e('Email log', 'ezit-core'); ?></h3>
        <button type="button" id="ezit-maillog-refresh" class="ezit-meta" title="<?php esc_attr_e('Refresh', 'ezit-core'); ?>"
                style="margin-left:auto;display:inline-flex;align-items:center;gap:5px;background:none;border:0;cursor:pointer;padding:2px 4px">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
            <?php esc_html_e('Refresh', 'ezit-core'); ?>
        </button>
        <?php if ($entries !== []) : ?>
        <button type="submit" name="ezit_clear_maillog" value="1" class="ezit-meta"
                style="background:none;border:0;cursor:pointer;text-decoration:underline">
            <?php esc_html_e('Clear log', 'ezit-core'); ?>
        </button>
        <?php endif; ?>
    </div>
    <p class="ezit-meta" style="margin-bottom:16px">
        <?php esc_html_e('Every email the site tried to send — newest first. Failed entries carry the transport error.', 'ezit-core'); ?>
    </p>

    <?php if ($entries === [] && $total === 0) : ?>
        <p class="ezit-meta" id="ezit-maillog-empty"><?php esc_html_e('No email activity yet.', 'ezit-core'); ?></p>
    <?php endif; ?>
    <div id="ezit-maillog-wrap" style="overflow-x:auto<?php echo $total === 0 ? ';display:none' : ''; ?>">
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
            <tbody id="ezit-maillog-rows">
            <?php foreach ($entries as $i => $row) :
                $status  = (string) ($row['status'] ?? '');
                $badge   = $status === 'sent' ? 'is-active' : ($status === 'blocked' ? 'is-blocked' : 'is-inactive');
                $label   = $status === 'sent' ? __('Sent', 'ezit-core')
                         : ($status === 'blocked' ? __('Blocked', 'ezit-core') : __('Failed', 'ezit-core'));
                $idx     = ($mlog_pg - 1) * $per_page + $i; // index into the full newest-first log
                $canview = ! empty($row['has_body']);
            ?>
                <tr class="ezit-maillog-row<?php echo $canview ? ' has-body' : ''; ?>" data-mlog-idx="<?php echo esc_attr((string) $idx); ?>"
                    data-mlog-from="<?php echo esc_attr($row['from'] ?? ''); ?>"
                    data-mlog-to="<?php echo esc_attr($row['to'] ?? ''); ?>"
                    data-mlog-when="<?php echo esc_attr($row['time'] ?? ''); ?>">
                    <td style="white-space:nowrap"><?php echo esc_html($row['time'] ?? ''); ?></td>
                    <td>
                        <span class="ezit-badge <?php echo esc_attr($badge); ?>">
                            <?php echo esc_html($label); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($row['to'] ?? ''); ?></td>
                    <td class="ezit-maillog-subj">
                        <?php echo esc_html($row['subject'] ?? ''); ?>
                        <?php if ($canview) : ?><span class="ezit-meta">&nbsp;&rarr;</span><?php endif; ?>
                    </td>
                    <td style="color:#b3483a;font-size:12.5px"><?php echo esc_html($row['error'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($nav !== '') : ?>
        <div class="ezit-paging" style="margin-top:12px"><?php echo $nav; // phpcs:ignore — paginate_links output ?></div>
        <?php endif; ?>
    </div>

    <?php /* Preview modal — iframe points at the stored body endpoint,
           so the email renders exactly as the recipient saw it. */ ?>
    <div id="ezit-maillog-modal" hidden>
        <div class="ezit-mailview" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Email preview', 'ezit-core'); ?>">
            <div class="ezit-mailview__bar">
                <strong id="ezit-mailview-title"><?php esc_html_e('Email preview', 'ezit-core'); ?></strong>
                <a id="ezit-mailview-open" href="#" target="_blank" rel="noopener"><?php esc_html_e('Open in new window', 'ezit-core'); ?></a>
                <button type="button" id="ezit-mailview-close" aria-label="<?php esc_attr_e('Close', 'ezit-core'); ?>">&times;</button>
            </div>
            <?php /* Inbox-style header — the same fields a mail client
                   shows above the message. */ ?>
            <dl class="ezit-mailview__meta">
                <div><dt><?php esc_html_e('From', 'ezit-core'); ?></dt><dd id="mv-from"></dd></div>
                <div><dt><?php esc_html_e('To', 'ezit-core'); ?></dt><dd id="mv-to"></dd></div>
                <div><dt><?php esc_html_e('Subject', 'ezit-core'); ?></dt><dd id="mv-subject"></dd></div>
                <div><dt><?php esc_html_e('Sent', 'ezit-core'); ?></dt><dd id="mv-when"></dd></div>
            </dl>
            <iframe id="ezit-mailview-frame" title="<?php esc_attr_e('Email preview', 'ezit-core'); ?>"></iframe>
        </div>
    </div>
</div>
