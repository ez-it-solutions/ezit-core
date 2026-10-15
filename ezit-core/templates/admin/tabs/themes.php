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

// Side-tab sections — the same nested EzIT Tabs component as Settings.
$sections = ['installed' => __('Site themes', 'ezit-core')];
if ($can_edit) {
    $sections['credit'] = __('Footer credit', 'ezit-core');
}
$section = sanitize_key($_GET['section'] ?? 'installed'); // phpcs:ignore
if (! isset($sections[$section])) {
    $section = 'installed';
}

// Theme inventory is server-side paginated — a site can have dozens of
// installed themes and a giant card grid gets unwieldy. Per-page choice
// sticks per user (user meta) so it survives reloads.
$per_opts = [6, 12, 24, 48];
$per_page = absint($_GET['themes_per_page'] ?? 0); // phpcs:ignore
if (in_array($per_page, $per_opts, true)) {
    update_user_meta(get_current_user_id(), 'ezit_themes_per_page', $per_page);
} else {
    $saved_pp = (int) get_user_meta(get_current_user_id(), 'ezit_themes_per_page', true);
    $per_page = in_array($saved_pp, $per_opts, true) ? $saved_pp : 6;
}
$page       = max(1, absint($_GET['themes_page'] ?? 1)); // phpcs:ignore
$total      = count($themes);
$pages      = max(1, (int) ceil($total / $per_page));
$page       = min($page, $pages);
$page_items = array_slice($themes, ($page - 1) * $per_page, $per_page);
// Light panel theme for the nested component.
$pvars = '--panel-bg:#eef0f2; --panel-fg:#14151f; --panel-muted:#5c5f6b;'
       . ' --panel-btn-bg:#14151f; --panel-btn-fg:#ffffff;'
       . ' --panel-deco:rgba(20,21,31,.05); --panel-progress:#76b900;';
?>
<div class="pg-sethead" data-ezt-anim>
    <span class="pg-sethead__chip" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#5a8f00" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18"/><path d="M9 9v12"/></svg>
    </span>
    <div class="pg-sethead__text">
        <span class="pg-sethead__eyebrow"><?php esc_html_e('Appearance', 'ezit-core'); ?></span>
        <h2><?php esc_html_e('Themes', 'ezit-core'); ?></h2>
        <p><?php esc_html_e('Every installed theme on this site — Ez IT products carry a license badge.', 'ezit-core'); ?></p>
    </div>
    <?php if ($can_edit) : ?>
    <div class="pg-sethead__side">
        <a class="pg-sethead__btn" href="<?php echo esc_url(admin_url('customize.php')); ?>"><?php esc_html_e('Open Customizer', 'ezit-core'); ?></a>
    </div>
    <?php endif; ?>
</div>

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

        <section class="ezt__panel<?php echo $section === 'installed' ? ' ezt__panel--active' : ''; ?>"
                 role="tabpanel" id="ezit-panel-installed" aria-labelledby="ezit-sub-installed"
                 <?php echo $section === 'installed' ? '' : 'hidden'; ?> data-ezt-panel
                 style="<?php echo esc_attr($pvars); ?>">
            <div class="ezt__panel-inner">
                <div class="ezit-themes">
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
                                    <a class="ezit-btn" href="<?php echo esc_url(admin_url('customize.php')); ?>"><?php esc_html_e('Customize', 'ezit-core'); ?></a>
                                <?php elseif ($can_swap) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('ezit_switch_theme', 'ezit_theme_nonce'); ?>
                                        <input type="hidden" name="action" value="ezit_switch_theme">
                                        <input type="hidden" name="stylesheet" value="<?php echo esc_attr($t['stylesheet']); ?>">
                                        <button type="submit" class="ezit-btn"><?php esc_html_e('Activate', 'ezit-core'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <a class="ezit-btn ezit-btn--ghost" href="<?php echo esc_url(admin_url('themes.php?theme=' . $t['stylesheet'])); ?>"><?php esc_html_e('Details', 'ezit-core'); ?></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php if ($pages > 1) : ?>
                <div class="ezit-paging">
                    <span class="ezit-paging__count">
                        <?php echo esc_html(sprintf(
                            /* translators: 1: first item on page, 2: last item, 3: total */
                            __('%1$d–%2$d of %3$d themes', 'ezit-core'),
                            ($page - 1) * $per_page + 1,
                            min($page * $per_page, $total),
                            $total
                        )); ?>
                    </span>
                    <span class="ezit-paging__links">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'base'      => add_query_arg(
                                ['themes_page' => '%#%', 'themes_per_page' => $per_page],
                                admin_url('admin.php?page=ezit-core&tab=themes&section=installed')
                            ),
                            'format'    => '',
                            'current'   => $page,
                            'total'     => $pages,
                            'prev_text' => '&lsaquo;',
                            'next_text' => '&rsaquo;',
                        ]));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($can_edit) : ?>
        <section class="ezt__panel<?php echo $section === 'credit' ? ' ezt__panel--active' : ''; ?>"
                 role="tabpanel" id="ezit-panel-credit" aria-labelledby="ezit-sub-credit"
                 <?php echo $section === 'credit' ? '' : 'hidden'; ?> data-ezt-panel
                 style="<?php echo esc_attr($pvars); ?>">
            <div class="ezt__panel-inner">
                <div class="ezit-card">
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
            </div>
        </section>
        <?php endif; ?>

    </div>
</div>
                            <span class="hint"><?php esc_html_e('Who the credit links to — leave blank for “Ez IT Solutions”.', 'ezit-core'); ?></span>
                        </div>
                        <button type="submit" class="ezit-btn"><?php esc_html_e('Save credit', 'ezit-core'); ?></button>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </div>
</div>
