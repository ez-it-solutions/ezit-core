<?php
/**
 * Flat UC page — full-bleed accent slab, big type, minimal chrome.
 * Countdown (if set) + client access login.
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
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($title); ?> — <?php echo esc_html($site); ?></title>
<style>
    :root { --a: <?php echo esc_attr($accent); ?>; }
    * { box-sizing: border-box; margin: 0; }
    body {
        min-height: 100svh; display: grid; place-items: center; padding: 24px;
        font: 16px/1.6 "Inter", system-ui, -apple-system, sans-serif;
        color: #0d0d0d; background: var(--a);
    }
    .card { width: 100%; max-width: 560px; text-align: center; }
    .logo { display: block; margin: 0 auto 30px; max-height: 56px; width: auto; }
    .site { font-size: 12px; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; opacity: .55; }
    h1 {
        font-size: clamp(2.2rem, 7vw, 3.6rem); font-weight: 800;
        letter-spacing: -.03em; line-height: 1.05; margin-bottom: 14px;
    }
    .msg { font-size: 16px; opacity: .72; max-width: 46ch; margin: 0 auto; }
    .cd { display: flex; justify-content: center; gap: 26px; margin-top: 34px; }
    .cd b { display: block; font-size: 34px; font-weight: 800; font-variant-numeric: tabular-nums; }
    .cd span { font-size: 10px; letter-spacing: .14em; text-transform: uppercase; opacity: .55; }
    .err { margin: 22px auto 0; max-width: 380px; padding: 10px 16px; border-radius: 10px; background: rgba(0,0,0,.85); color: #fda4af; font-size: 13px; }
    .login { margin: 34px auto 0; max-width: 380px; display: grid; gap: 10px; }
    .login input {
        width: 100%; padding: 13px 16px; border-radius: 10px; font: inherit; font-size: 14px;
        border: 2px solid rgba(0,0,0,.25); background: rgba(255,255,255,.55); color: #0d0d0d; outline: none;
    }
    .login input::placeholder { color: rgba(0,0,0,.4); }
    .login input:focus { border-color: #0d0d0d; }
    .login button {
        padding: 13px 16px; border: 0; border-radius: 10px; cursor: pointer;
        background: #0d0d0d; color: #fff; font: inherit; font-size: 14px; font-weight: 700;
    }
    .own { margin-top: 22px; font-size: 12px; opacity: .45; }
    .own a { color: inherit; }
</style>
</head>
<body>
<div class="card">
    <?php if ($logo !== '') : ?>
        <img class="logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site); ?>">
    <?php else : ?>
        <p class="site"><?php echo esc_html($site); ?></p>
    <?php endif; ?>
    <h1><?php echo esc_html($title); ?></h1>
    <p class="msg"><?php echo esc_html($message); ?></p>

    <?php if ($countdown !== '') : ?>
    <div class="cd" data-countdown="<?php echo esc_attr(gmdate('c', strtotime($countdown))); ?>">
        <div><b data-u="days">0</b><span>Days</span></div>
        <div><b data-u="hours">0</b><span>Hours</span></div>
        <div><b data-u="mins">0</b><span>Min</span></div>
        <div><b data-u="secs">0</b><span>Sec</span></div>
    </div>
    <?php endif; ?>

    <?php if ($err !== '') : ?><p class="err"><?php echo esc_html($err); ?></p><?php endif; ?>

    <form class="login" method="post" action="<?php echo esc_url(home_url('/')); ?>">
        <input type="hidden" name="ezit_gate_login" value="1">
        <input type="text" name="ezit_gate_user" placeholder="<?php esc_attr_e('Username', 'ezit-core'); ?>" required autocomplete="username">
        <input type="password" name="ezit_gate_pass" placeholder="<?php esc_attr_e('Password', 'ezit-core'); ?>" required autocomplete="current-password">
        <button type="submit"><?php esc_html_e('Client access', 'ezit-core'); ?></button>
    </form>
    <p class="own"><?php echo esc_html($site); ?> — <?php esc_html_e('powered by', 'ezit-core'); ?> <a href="http://www.Ez-IT-Solutions.com" target="_blank" rel="noopener" style="color:inherit">Ez IT Solutions</a></p>
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
