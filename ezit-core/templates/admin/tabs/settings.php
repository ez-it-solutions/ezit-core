<?php
/**
 * Settings tab — pg-sethead toolbar + license server config.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}
?>
<form id="ezit-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('ezit_settings', 'ezit_settings_nonce'); ?>
    <input type="hidden" name="action" value="ezit_settings">
    <input type="hidden" name="section" value="<?php echo esc_attr(sanitize_key($_GET['section'] ?? '')); /* phpcs:ignore */ ?>">

    <div class="pg-sethead" data-ezt-anim>
        <span class="pg-sethead__chip" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#5a8f00" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.2a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.2a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3h0a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.2a1.6 1.6 0 0 0 1 1.5h0a1.6 1.6 0 0 0 1.8-.3l.1.1a2 2 0 1 1 2.8 2.8l.1.1a1.6 1.6 0 0 0 .3 1.8v0a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.2a1.6 1.6 0 0 0-1.5 1z"/></svg>
        </span>
        <div class="pg-sethead__text">
            <span class="pg-sethead__eyebrow"><?php esc_html_e('Plugin Settings', 'ezit-core'); ?></span>
            <h2><?php esc_html_e('Settings', 'ezit-core'); ?></h2>
            <p><?php esc_html_e('How Ez IT Core talks to the outside world.', 'ezit-core'); ?></p>
        </div>
        <div class="pg-sethead__side">
            <button type="submit" class="pg-sethead__btn"><?php esc_html_e('Save all', 'ezit-core'); ?></button>
        </div>
    </div>

    <?php
    // Settings sub-navigation — the same EzIT Tabs component as the top
    // bar, in its default side-column direction. data-ezt-interval="0"
    // disables auto-rotate; ?section= deep-links each panel.
    $sections = [
        'server' => __('License server', 'ezit-core'),
        'gate'   => __('Site gate', 'ezit-core'),
        'page'   => __('Holding page', 'ezit-core'),
        'login'  => __('Login page', 'ezit-core'),
    ];
    $section = sanitize_key($_GET['section'] ?? 'server'); // phpcs:ignore
    if (! isset($sections[$section])) {
        $section = 'server';
    }
    // Light panel theme for the nested component.
    $pvars = '--panel-bg:#eef0f2; --panel-fg:#14151f; --panel-muted:#5c5f6b;'
           . ' --panel-btn-bg:#14151f; --panel-btn-fg:#ffffff;'
           . ' --panel-deco:rgba(20,21,31,.05); --panel-progress:#76b900;';
    ?>
    <div class="ezt ezt-subnav" data-ezt data-ezt-fill="off" data-ezt-interval="0"
         data-ezt-timer="none" data-ezt-anim="pull" data-ezt-url-param="section">
        <ul class="ezt__tabs" role="tablist" aria-orientation="vertical">
            <?php foreach ($sections as $id => $label) : ?>
            <li>
                <button type="button" class="ezt__tab" role="tab" id="ezit-sub-<?php echo esc_attr($id); ?>"
                        aria-controls="ezit-panel-<?php echo esc_attr($id); ?>"
                        aria-selected="<?php echo $id === $section ? 'true' : 'false'; ?>"
                        <?php echo $id === $section ? '' : 'tabindex="-1"'; ?> data-ezt-tab>
                    <span class="ezt__tab-label"><?php echo esc_html($label); ?></span>
                    <span class="ezt__progress" aria-hidden="true"><span class="ezt__progress-bar"></span></span>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="ezt__panels">
            <?php foreach ($sections as $id => $label) : ?>
            <section class="ezt__panel<?php echo $id === $section ? ' ezt__panel--active' : ''; ?>"
                     role="tabpanel" id="ezit-panel-<?php echo esc_attr($id); ?>"
                     aria-labelledby="ezit-sub-<?php echo esc_attr($id); ?>"
                     <?php echo $id === $section ? '' : 'hidden'; ?> data-ezt-panel
                     style="<?php echo esc_attr($pvars); ?>">
                <div class="ezt__panel-inner">
                    <?php include EZIT_CORE_PATH . 'templates/admin/settings/' . $id . '.php'; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</form>
