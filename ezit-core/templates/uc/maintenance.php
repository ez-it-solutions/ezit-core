<?php
/**
 * Maintenance UC page — richer but still minimal.
 *
 * Sections: status badge, headline + message, an info grid (expected back /
 * contact), optional countdown, and a quiet client-access login.
 *
 * @var string $title
 * @var string $message
 * @var string $err
 * @var string $site
 * @var array  $brand { accent, bg, logo, countdown }
 */
$accent    = $brand['accent'];
$countdown = $brand['countdown'];
$logo      = $brand['logo'];
$contact   = apply_filters('ezit_uc_contact', get_option('admin_email'));
$back_at   = $countdown !== '' ? strtotime($countdown) : false;
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
<style>
    :root { --a: <?php echo esc_attr($accent); ?>; }
    * { box-sizing: border-box; margin: 0; }
    body {
        min-height: 100svh; display: grid; place-items: center; padding: 32px 20px;
        font-size: 15px; line-height: 1.65;
        /* Font-family, text color + page background come from the theme. */
    }
    .wrap { width: 100%; max-width: 460px; }
    .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 26px; }
    .logo { display: block; max-height: 40px; width: auto; }
    .site { font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
    .badge {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
        padding: 5px 12px; border-radius: 999px;
        background: color-mix(in srgb, var(--a) 14%, #fff);
        color: color-mix(in srgb, var(--a) 75%, #000);
        border: 1px solid color-mix(in srgb, var(--a) 30%, transparent);
    }
    .badge::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--a); }
    .card {
        background: #fff; border: 1px solid #e6e8e2; border-radius: 18px;
        padding: 36px 34px; box-shadow: 0 20px 50px rgba(27,29,35,.06);
    }
    h1 { font-size: clamp(1.45rem, 4vw, 1.85rem); font-weight: 600; margin-bottom: 10px; text-wrap: balance; }
    .msg { color: #5c5f6b; font-size: 14.5px; text-wrap: pretty; }
    .badge, .login h2, .cd span { font-family: Arial, Helvetica, sans-serif; }
    .info { margin-top: 24px; border-top: 1px solid #eef0ea; }
    .info div {
        display: flex; align-items: baseline; justify-content: space-between; gap: 16px;
        padding: 12px 0; border-bottom: 1px solid #eef0ea; font-size: 13.5px;
    }
    .info dt { color: #9a9da6; font-weight: 600; }
    .info dd { font-weight: 600; text-align: right; }
    .info dd a { color: var(--a); text-decoration: none; }
    .cd { display: flex; justify-content: center; gap: 20px; margin-top: 24px; }
    .cd b { display: block; font-size: 27px; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--a); }
    .cd span { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: #9a9da6; }
    .err { margin: 22px 0 0; padding: 9px 14px; border-radius: 9px; background: #fef1f2; color: #be123c; font-size: 13px; }
    .login { margin-top: 26px; border-top: 1px dashed #e6e8e2; padding-top: 22px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .login h2 { grid-column: 1/-1; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #9a9da6; margin-bottom: 4px; }
    .login input {
        padding: 11px 13px; border: 1px solid #e0e2da; border-radius: 9px;
        font: 13px Arial, Helvetica, sans-serif; outline: none; background: #fafbf7;
    }
    .login input:focus { border-color: var(--a); background: #fff; }
    .login button {
        grid-column: 1/-1; padding: 11px; border: 0; border-radius: 9px; cursor: pointer;
        background: var(--a); color: #fff; font: 700 13px Arial, Helvetica, sans-serif;
    }
    .foot { margin-top: 26px; text-align: center; font-size: 12px; color: #9a9da6; }
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <?php if ($logo !== '') : ?>
            <img class="logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site); ?>">
        <?php else : ?>
            <span class="site"><?php echo esc_html($site); ?></span>
        <?php endif; ?>
        <span class="badge"><?php esc_html_e('Maintenance', 'ezit-core'); ?></span>
    </div>

    <div class="card">
        <h1><?php echo esc_html($title); ?></h1>
        <p class="msg"><?php echo nl2br(esc_html($message)); ?></p>

        <dl class="info">
            <?php if ($back_at) : ?>
            <div>
                <dt><?php esc_html_e('Expected back', 'ezit-core'); ?></dt>
                <dd><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $back_at)); ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($contact !== '') : ?>
            <div>
                <dt><?php esc_html_e('Questions', 'ezit-core'); ?></dt>
                <dd><a href="mailto:<?php echo esc_attr($contact); ?>"><?php echo esc_html($contact); ?></a></dd>
            </div>
            <?php endif; ?>
        </dl>

        <?php if ($countdown !== '') : ?>
        <div class="cd" data-countdown="<?php echo esc_attr(gmdate('c', $back_at)); ?>">
            <div><b data-u="days">0</b><span>Days</span></div>
            <div><b data-u="hours">0</b><span>Hrs</span></div>
            <div><b data-u="mins">0</b><span>Min</span></div>
            <div><b data-u="secs">0</b><span>Sec</span></div>
        </div>
        <?php endif; ?>

        <?php if ($err !== '') : ?><p class="err"><?php echo esc_html($err); ?></p><?php endif; ?>

        <form class="login" method="post" action="<?php echo esc_url(home_url('/')); ?>">
            <h2><?php esc_html_e('Client access', 'ezit-core'); ?></h2>
            <input type="hidden" name="ezit_gate_login" value="1">
            <input type="text" name="ezit_gate_user" placeholder="<?php esc_attr_e('Username', 'ezit-core'); ?>" required autocomplete="username">
            <input type="password" name="ezit_gate_pass" placeholder="<?php esc_attr_e('Password', 'ezit-core'); ?>" required autocomplete="current-password">
            <button type="submit"><?php esc_html_e('Log in', 'ezit-core'); ?></button>
        </form>
    </div>

    <p class="foot"><?php echo esc_html($site); ?> — <?php esc_html_e('powered by', 'ezit-core'); ?> <a href="http://www.Ez-IT-Solutions.com" target="_blank" rel="noopener" style="color:inherit">Ez IT Solutions</a></p>
</div>
<script>
(function(){var e=document.querySelector("[data-countdown]");if(!e)return;
var t=new Date(e.dataset.countdown).getTime();if(isNaN(t)){e.style.display="none";return}
function u(){var s=Math.max(0,Math.floor((t-Date.now())/1e3));
e.querySelector('[data-u="days"]').textContent=Math.floor(s/86400);
e.querySelector('[data-u="hours"]').textContent=Math.floor(s%86400/3600);
e.querySelector('[data-u="mins"]').textContent=Math.floor(s%3600/60);
e.querySelector('[data-u="secs"]').textContent=s%60}
u();setInterval(u,1e3)})();
</script>
</body>
</html>
