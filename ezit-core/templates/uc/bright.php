<?php
/**
 * UC layout: Bright — light, playful, accent-driven. (Yellow hazard
 * screenshot style, colorized by the chosen accent.)
 * Vars: $title, $message, $err, $site, $brand, $post_to
 */

if (! defined('ABSPATH')) {
    exit;
}
$a = esc_attr($brand['accent']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html($title); ?> — <?php echo esc_html($site); ?></title>
<style>
    * { box-sizing: border-box; margin: 0; }
    body {
        min-height: 100vh; display: grid; place-items: center; padding: 2.5rem 1.5rem;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: <?php echo $a; ?>; color: #1c1c14;
        text-align: center;
    }
    .wrap { max-width: 640px; width: 100%; }
    .icon {
        width: 84px; height: 84px; margin: 0 auto 1.6rem; border-radius: 24px;
        display: grid; place-items: center;
        background: rgba(0,0,0,.1); border: 2px solid rgba(0,0,0,.14);
    }
    .icon svg { width: 44px; height: 44px; }
    .eyebrow { font-size: .8rem; font-weight: 800; letter-spacing: .28em; text-transform: uppercase; opacity: .7; margin-bottom: .9rem; }
    h1 { font-size: clamp(2.2rem, 7vw, 3.6rem); font-weight: 900; letter-spacing: -.03em; margin-bottom: 1rem; text-wrap: balance; }
    p.msg { font-size: 1.05rem; line-height: 1.7; opacity: .78; max-width: 460px; margin: 0 auto 2.4rem; }
    .cd { display: flex; gap: .7rem; justify-content: center; margin-bottom: 2.6rem; flex-wrap: wrap; }
    .cd .tile {
        min-width: 82px; padding: .9rem .5rem .75rem; border-radius: 12px;
        background: rgba(0,0,0,.88); color: <?php echo $a; ?>;
    }
    .cd .tile b { display: block; font-size: 1.6rem; font-weight: 800; line-height: 1; }
    .cd .tile span { display: block; margin-top: .3rem; font-size: .58rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.7); }
    .login {
        max-width: 400px; margin: 0 auto; background: rgba(255,255,255,.92);
        border-radius: 14px; padding: 1.5rem; text-align: left;
        box-shadow: 0 12px 32px rgba(0,0,0,.14);
    }
    .login h2 { font-size: .75rem; letter-spacing: .16em; text-transform: uppercase; color: #6b6d55; margin-bottom: .9rem; font-weight: 700; }
    .login input {
        width: 100%; padding: .7rem .85rem; margin-bottom: .6rem; border-radius: 8px;
        border: 1px solid #dcded0; background: #fff; font-size: .95rem;
    }
    .login input:focus { outline: none; border-color: #1c1c14; }
    .login button {
        width: 100%; padding: .8rem; border: 0; border-radius: 8px; cursor: pointer;
        background: #1c1c14; color: <?php echo $a; ?>; font-weight: 700; font-size: .95rem;
    }
    .login .owner { font-size: .72rem; color: #8a8c78; margin: .7rem 0 0; }
    .login .owner a { color: #6b6d55; }
    .err {
        background: #fdecec; border: 1px solid #f3bcbc; color: #a32727;
        padding: .55rem .75rem; border-radius: 8px; font-size: .85rem; margin-bottom: .7rem;
    }
    .foot { margin-top: 2.6rem; font-size: .75rem; opacity: .6; }
</style>
</head>
<body>
<div class="wrap">
    <div class="icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="#1c1c14" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 20h20"/><path d="M6 20V10l6-6 6 6v10"/><path d="M12 4v3"/><circle cx="12" cy="15" r="2"/>
        </svg>
    </div>
    <div class="eyebrow"><?php esc_html_e('Website under construction', 'ezit-core'); ?></div>
    <h1><?php echo esc_html($title); ?></h1>
    <p class="msg"><?php echo nl2br(esc_html($message)); ?></p>

    <?php if ($brand['countdown'] !== '') : ?>
    <div class="cd" data-until="<?php echo esc_attr($brand['countdown']); ?>">
        <div class="tile"><b data-u="d">00</b><span><?php esc_html_e('Days', 'ezit-core'); ?></span></div>
        <div class="tile"><b data-u="h">00</b><span><?php esc_html_e('Hours', 'ezit-core'); ?></span></div>
        <div class="tile"><b data-u="m">00</b><span><?php esc_html_e('Minutes', 'ezit-core'); ?></span></div>
        <div class="tile"><b data-u="s">00</b><span><?php esc_html_e('Seconds', 'ezit-core'); ?></span></div>
    </div>
    <?php endif; ?>

    <form class="login" method="post" action="<?php echo esc_attr($post_to); ?>">
        <h2><?php esc_html_e('Client access', 'ezit-core'); ?></h2>
        <?php if ($err !== '') : ?>
            <div class="err"><?php echo esc_html($err); ?></div>
        <?php endif; ?>
        <input type="hidden" name="ezit_gate_login" value="1">
        <input name="ezit_gate_user" type="text" placeholder="<?php esc_attr_e('Username', 'ezit-core'); ?>" autocomplete="username" required>
        <input name="ezit_gate_pass" type="password" placeholder="<?php esc_attr_e('Password', 'ezit-core'); ?>" autocomplete="current-password" required>
        <button type="submit"><?php esc_html_e('Log in', 'ezit-core'); ?></button>
    </form>

    <p class="foot"><?php echo esc_html($site); ?> — <?php esc_html_e('powered by', 'ezit-core'); ?> <a href="http://www.Ez-IT-Solutions.com" target="_blank" rel="noopener" style="color:inherit">Ez IT Solutions</a></p>
</div>
<script>
(function(){
    var el=document.querySelector('[data-until]'); if(!el) return;
    var t=Date.parse(el.dataset.until);
    function tick(){
        var d=Math.max(0,t-Date.now()), o={};
        o.d=Math.floor(d/864e5); d%=864e5;
        o.h=Math.floor(d/36e5);  d%=36e5;
        o.m=Math.floor(d/6e4);   d%=6e4;
        o.s=Math.floor(d/1e3);
        var u=el.querySelectorAll('[data-u]');
        for(var i=0;i<u.length;i++){
            u[i].textContent=String(o[u[i].dataset.u]).padStart(2,'0');
        }
    }
    tick(); setInterval(tick,1000);
})();
</script>
</body>
</html>
