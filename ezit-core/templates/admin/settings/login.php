<?php
/**
 * Settings section — branded wp-login.php appearance.
 * Vars in scope: $core
 */
if (! defined('ABSPATH')) {
    exit;
}

$login = EzIT_Login::settings();
?>
<div class="ezit-card">
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/><circle cx="6.5" cy="6" r=".8" fill="currentColor"/><circle cx="9.5" cy="6" r=".8" fill="currentColor"/></svg>
        <h3><?php esc_html_e('Login page', 'ezit-core'); ?></h3>
    </div>
    <p class="ezit-meta" style="margin-bottom:14px">
        <?php esc_html_e('Brands wp-login.php for this site — stored in Core, so it survives theme switches.', 'ezit-core'); ?>
    </p>

    <div class="ezit-field">
        <label for="ezit_login_logo"><?php esc_html_e('Logo image URL', 'ezit-core'); ?></label>
        <input id="ezit_login_logo" name="ezit_login_logo" type="url"
               value="<?php echo esc_attr($login['logo']); ?>"
               placeholder="<?php echo esc_attr(home_url('/wp-content/uploads/logo.svg')); ?>">
        <span class="hint"><?php esc_html_e('Empty = Ez IT bolt. Paste a URL from Media Library (any height; it renders ~84px tall).', 'ezit-core'); ?></span>
    </div>

    <div class="ezit-field-grid">
        <div class="ezit-field">
            <label for="ezit_login_accent"><?php esc_html_e('Accent color', 'ezit-core'); ?></label>
            <input id="ezit_login_accent" name="ezit_login_accent" type="text" class="ezit-color"
                   data-default-color="#76b900"
                   value="<?php echo esc_attr($login['accent'] !== '' ? $login['accent'] : '#76b900'); ?>">
            <span class="hint"><?php esc_html_e('Buttons, links and the form’s top border.', 'ezit-core'); ?></span>
        </div>
        <div class="ezit-field">
            <label for="ezit_login_bg"><?php esc_html_e('Background color', 'ezit-core'); ?></label>
            <input id="ezit_login_bg" name="ezit_login_bg" type="text" class="ezit-color"
                   data-default-color="#191919"
                   value="<?php echo esc_attr($login['bg'] !== '' ? $login['bg'] : '#191919'); ?>">
            <span class="hint"><?php esc_html_e('Page background behind the form.', 'ezit-core'); ?></span>
        </div>
    </div>

    <div class="ezit-field">
        <label for="ezit_login_message"><?php esc_html_e('Message (optional)', 'ezit-core'); ?></label>
        <textarea id="ezit_login_message" name="ezit_login_message" rows="2"
                  placeholder="<?php esc_attr_e('e.g. Client portal — sign in with the credentials Ez IT gave you.', 'ezit-core'); ?>"><?php echo esc_textarea($login['message']); ?></textarea>
        <span class="hint"><?php esc_html_e('Shown above the form. Blank = no message.', 'ezit-core'); ?></span>
    </div>

    <p class="ezit-meta">
        <a href="<?php echo esc_url(wp_login_url()); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open the login page', 'ezit-core'); ?> ↗</a>
    </p>
</div>
