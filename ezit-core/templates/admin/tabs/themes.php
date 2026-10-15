<?php
/**
 * Themes tab — installed theme inventory, in-plugin activation,
 * footer credit controls.
 * Vars in scope: $core, $stats
 */
if (! defined('ABSPATH')) {
    exit;
}

$themes   = EzIT_Themes::inventory($core);
$can_edit = current_user_can('edit_theme_options');
$can_swap = current_user_can('switch_themes');

$credit_on  = (bool) get_theme_mod(EzIT_Themes::CREDIT_ON, true);
$credit_txt = (string) get_theme_mod(EzIT_Themes::CREDIT_TXT, '');
?>
<div class="ezt__brand" data-ezt-anim>
    <span class="ezt__brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#76b900" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/><path d="M9 9v12"/></svg>
    </span>
    <span class="ezt__brand-name"><?php esc_html_e('Themes', 'ezit-core'); ?></span>
</div>
<h2 class="ezt__heading" data-ezt-anim><?php esc_html_e('Site themes', 'ezit-core'); ?></h2>
<p class="ezt__text" data-ezt-anim>
    <?php esc_html_e('Every installed theme on this site. Ez IT products carry a license badge — switch themes right here.', 'ezit-core'); ?>
</p>

<div class="ezit-themes" data-ezt-anim>
<?php foreach ($themes as $t) : ?>
    <div class="ezit-theme<?php echo $t['is_active'] ? ' is-active' : ''; ?>">
        <div class="ezit-theme__shot">
            <?php if ($t['screenshot']) : ?>
                <img src="<?php echo esc_url($t['screenshot']); ?>" alt="" loading="lazy">
            <?php else : ?>
                <div class="ezit-theme__blank"><svg viewBox="0 0 24 24" width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m14.5 9.5-5 5M9.5 9.5l5 5"/></svg></div>
            <?php endif; ?>
            <?php if ($t['is_active']) : ?>
                <span class="ezit-theme__flag"><?php esc_html_e('Active', 'ezit-core'); ?></span>
            <?php endif; ?>
        </div>
        <div class="ezit-theme__body">
            <div class="ezit-theme__title">
                <strong><?php echo esc_html($t['name']); ?></strong>
                <span class="ezit-muted"><?php echo esc_html('v' . ($t['version'] !== '' ? $t['version'] : '—')); ?></span>
            </div>
            <?php if ($t['author'] !== '') : ?>
                <p class="ezit-meta"><?php echo esc_html($t['author']); ?></p>
            <?php endif; ?>
            <div class="ezit-theme__badges">
                <?php if ($t['ezit']) : ?>
                    <span class="ezit-badge is-active"><?php esc_html_e('Ez IT', 'ezit-core'); ?></span>
                    <?php if ($t['registered']) : ?>
                        <span class="ezit-badge <?php echo $t['licensed'] ? 'is-active' : 'is-inactive'; ?>">
                            <?php echo $t['licensed'] ? esc_html__('Licensed', 'ezit-core') : esc_html__('Unlicensed', 'ezit-core'); ?>
                        </span>
                    <?php else : ?>
                        <span class="ezit-badge is-inactive"><?php esc_html_e('Not registered', 'ezit-core'); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="ezit-theme__actions">
                <?php if ($t['is_active']) : ?>
                    <a class="ezit-btn ezit-btn--ghost" href="<?php echo esc_url(admin_url('customize.php')); ?>"><?php esc_html_e('Customize', 'ezit-core'); ?></a>
                <?php elseif ($can_swap) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('ezit_switch_theme', 'ezit_theme_nonce'); ?>
                        <input type="hidden" name="action" value="ezit_switch_theme">
                        <input type="hidden" name="stylesheet" value="<?php echo esc_attr($t['stylesheet']); ?>">
                        <button type="submit" class="ezit-btn"><?php esc_html_e('Activate', 'ezit-core'); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($can_edit) : ?>
<div class="ezit-card" data-ezt-anim>
    <div class="ezit-card__head">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        <h3><?php esc_html_e('Footer credit', 'ezit-core'); ?></h3>
    </div>
    <p class="ezit-meta" style="margin-bottom:14px">
        <?php esc_html_e('Controls the “Made with ♥ by Ez IT Solutions” credit shown by the active theme. Saved with the theme, so each Ez IT product reads the same setting.', 'ezit-core'); ?>
    </p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ezit_credit', 'ezit_credit_nonce'); ?>
        <input type="hidden" name="action" value="ezit_credit">
        <div class="ezit-field ezit-field--check">
            <label>
                <input type="checkbox" name="credit_enabled" value="1" <?php checked($credit_on); ?>>
                <?php esc_html_e('Show the footer credit on this site', 'ezit-core'); ?>
            </label>
        </div>
        <div class="ezit-field">
            <label for="ezit-credit-text"><?php esc_html_e('Credit text', 'ezit-core'); ?></label>
            <input type="text" id="ezit-credit-text" name="credit_text" value="<?php echo esc_attr($credit_txt); ?>" placeholder="<?php esc_attr_e('Ez IT Solutions', 'ezit-core'); ?>">
            <span class="hint"><?php esc_html_e('Who the credit links to — leave blank for “Ez IT Solutions”.', 'ezit-core'); ?></span>
        </div>
        <button type="submit" class="ezit-btn"><?php esc_html_e('Save credit', 'ezit-core'); ?></button>
    </form>
</div>
<?php endif; ?>
