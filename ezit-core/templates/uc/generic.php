<?php
/**
 * Generic UC page — matches the ACTIVE THEME.
 *
 * Loads the theme's own stylesheet (+ block-theme global styles) so the
 * visitor sees site-native typography and colors. No login form, no
 * countdown — a clean "we'll be right back" for license/maintenance use.
 *
 * @var string $title
 * @var string $message
 * @var string $err
 * @var string $site
 * @var array  $brand { accent, bg, logo, countdown }
 */
$accent = $brand['accent'];
$logo   = $brand['logo'];

// Block-theme global styles complement the theme css list ($theme_css,
// provided by render_uc) so the page feels native to the installed theme.
$global_css = function_exists('wp_get_global_stylesheet') ? wp_get_global_stylesheet() : '';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($title); ?> — <?php echo esc_html($site); ?></title>
<?php foreach ((array) $theme_css as $u) : ?>
<link rel="stylesheet" href="<?php echo esc_url($u); ?>">
<?php endforeach; ?>
<?php if ($global_css !== '') : ?>
<style><?php echo $global_css; // phpcs:ignore ?></style>
<?php endif; ?>
<style>
    html { height: 100%; }
    body {
        margin: 0; min-height: 100svh;
        display: grid; place-items: center;
        padding: 32px 24px; box-sizing: border-box;
        /* Theme stylesheet supplies font-family + colors. */
    }
    .uc { width: 100%; max-width: 540px; text-align: center; }
    .uc-logo { display: block; margin: 0 auto 28px; max-height: 64px; width: auto; }
    .uc-site {
        margin: 0 0 26px; font-size: 14px; font-weight: 700;
        letter-spacing: .14em; text-transform: uppercase; opacity: .6;
    }
    .uc-rule {
        width: 44px; height: 4px; margin: 0 auto 26px;
        border: 0; border-radius: 99px; background: <?php echo esc_attr($accent); ?>;
    }
    .uc h1 {
        margin: 0 0 16px; font-size: clamp(1.7rem, 4vw, 2.4rem);
        line-height: 1.2; font-weight: 700; text-wrap: balance;
    }
    .uc-msg { margin: 0; font-size: 1.05rem; line-height: 1.65; opacity: .75; text-wrap: pretty; }
    .uc-msg p { margin: 0 0 .8em; }
    .uc-foot { margin: 40px 0 0; font-size: 12px; opacity: .45; }
</style>
</head>
<body>
<main class="uc">
    <?php if ($logo !== '') : ?>
        <img class="uc-logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site); ?>">
    <?php else : ?>
        <p class="uc-site"><?php echo esc_html($site); ?></p>
    <?php endif; ?>

    <hr class="uc-rule">
    <h1><?php echo esc_html($title); ?></h1>
    <div class="uc-msg"><?php echo wp_kses_post(wpautop($message)); ?></div>

    <p class="uc-foot"><?php echo esc_html($site); ?> — <?php esc_html_e('powered by', 'ezit-core'); ?> <a href="http://www.Ez-IT-Solutions.com" target="_blank" rel="noopener" style="color:inherit">Ez IT Solutions</a></p>
</main>
</body>
</html>
