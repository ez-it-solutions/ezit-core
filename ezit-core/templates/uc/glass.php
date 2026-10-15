<?php
/**
 * Glass UC page — frosted-glass card over an accent-tinted photo/gradient.
 * Countdown (if set) + client access login.
 *
 * @var string $title
 * @var string $message
 * @var string $err
 * @var string $site
 * @var array  $brand { accent, bg, logo, countdown }
 */
$accent    = $brand['accent'];
$bg        = $brand['bg'];
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
        font: 16px/1.6 "Inter", system-ui, -apple-system, sans-serif; color: #fff;
        background:
            linear-gradient(160deg, rgba(10,11,14,.72), rgba(10,11,14,.55)),
            <?php echo $bg !== '' ? 'url(' . esc_url($bg) . ') center/cover no-repeat fixed,' : ''; ?>
            radial-gradient(120% 120% at 20% 10%, color-mix(in srgb, var(--a) 55%, #0a0b0e), #0a0b0e);
        background-color: #0a0b0e;
    }
    .card {
        width: 100%; max-width: 480px; padding: 52px 44px; text-align: center;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.16); border-radius: 24px;
        backdrop-filter: blur(18px) saturate(1.3);
        -webkit-backdrop-filter: blur(18px) saturate(1.3);
        box-shadow: 0 30px 80px rgba(0,0,0,.45);
    }
    .logo { display: block; margin: 0 auto 26px; max-height: 52px; width: auto; }
    .site { font-size: 12px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; opacity: .55; }
    .bar { width: 40px; height: 3px; margin: 18px auto; border-radius: 99px; background: var(--a); }
    h1 { font-size: clamp(1.6rem, 4vw, 2.1rem); font-weight: 800; letter-spacing: -.02em; margin-bottom: 12px; }
    .msg { font-size: 15px; opacity: .8; }
    .cd { display: flex; justify-content: center; gap: 12px; margin-top: 28px; }
    .cd div { min-width: 66px; padding: 12px 8px; border-radius: 14px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.14); }
    .cd b { display: block; font-size: 26px; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--a); }
    .cd span { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; opacity: .6; }
    .err { margin: 20px auto 0; max-width: 340px; padding: 9px 14px; border-radius: 10px; background: rgba(244,63,94,.15); border: 1px solid rgba(244,63,94,.35); color: #fda4af; font-size: 13px; }
    .login { margin-top: 28px; display: grid; gap: 10px; }
    .login input {
        width: 100%; padding: 12px 16px; border-radius: 12px;
        border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.07);
        color: #fff; font: inherit; font-size: 14px; outline: none;
    }
    .login input::placeholder { color: rgba(255,255,255,.4); }
    .login input:focus { border-color: var(--a); }
    .login button {
        padding: 12px 16px; border: 0; border-radius: 12px; cursor: pointer;
        background: var(--a); color: #101010; font: inherit; font-size: 14px; font-weight: 700;
    }
    .own { margin-top: 20px; font-size: 12px; opacity: .4; }
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
    <div class="bar"></div>
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
