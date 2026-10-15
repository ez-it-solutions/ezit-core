<?php
/**
 * UC layout: Split — brand + login panel left, headline + countdown
 * over photo right. (GATE 33 / ThemesCave style.)
 * Vars: $title, $message, $err, $site, $brand, $post_to
 */

if (! defined('ABSPATH')) {
    exit;
}
$a  = esc_attr($brand['accent']);
$bg = $brand['bg'] !== '' ? "url('" . esc_url($brand['bg']) . "')" : 'none';
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
        min-height: 100vh; display: grid; grid-template-columns: 420px 1fr;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: #14161a;
    }
    /* Left panel */
    .panel {
        display: flex; flex-direction: column; gap: 1.6rem;
        padding: clamp(1.8rem, 4vw, 3.2rem); background: #fff; color: #16161a;
    }
    .mark { font-size: 1.3rem; font-weight: 800; letter-spacing: -.02em; }
    .mark img { max-height: 44px; }
    .login { margin-top: auto; }
    .login h2 {
        font-size: .78rem; letter-spacing: .18em; text-transform: uppercase;
        color: #8a8d99; font-weight: 700; margin-bottom: 1rem;
    }
    .login input {
        width: 100%; padding: .8rem .95rem; margin-bottom: .7rem; border-radius: 8px;
        border: 1px solid #e2e4ea; background: #f7f8fa; font-size: .95rem; color: #16161a;
    }
    .login input:focus { outline: none; border-color: <?php echo $a; ?>; background: #fff; }
    .login button {
        width: 100%; padding: .85rem; border: 0; border-radius: 8px; cursor: pointer;
        background: <?php echo $a; ?>; color: #0e130c; font-weight: 700; font-size: .95rem;
    }
    .login .owner { font-size: .75rem; color: #9a9da8; margin: .8rem 0 0; }
    .login .owner a { color: #6b6d76; }
    .err {
        background: #fdecec; border: 1px solid #f3bcbc; color: #a32727;
        padding: .6rem .8rem; border-radius: 8px; font-size: .85rem; margin-bottom: .8rem;
    }
    .foot { font-size: .72rem; color: #9a9da8; margin-top: 1.6rem; }
    /* Right content */
    .stage {
        display: grid; place-items: center; text-align: center; padding: 3rem 2rem;
        color: #fff;
        background:
            linear-gradient(rgba(10,12,14,.62), rgba(10,12,14,.75)),
            <?php echo $bg; ?> center/cover no-repeat,
            #1a1d24 radial-gradient(ellipse at top, #262a35 0%, #1a1d24 70%);
    }
    .eyebrow {
        color: <?php echo $a; ?>; font-size: .78rem; font-weight: 700;
        letter-spacing: .3em; text-transform: uppercase; margin-bottom: 1rem;
    }
    .stage h1 { font-size: clamp(2.2rem, 5vw, 3.8rem); font-weight: 800; letter-spacing: -.02em; margin-bottom: 1rem; text-wrap: balance; }
    .stage .msg { color: rgba(255,255,255,.7); line-height: 1.7; max-width: 480px; margin: 0 auto 2.2rem; }
    .cd { display: flex; gap: .8rem; justify-content: center; flex-wrap: wrap; }
    .cd .tile {
        min-width: 84px; padding: .95rem .5rem .8rem; border-radius: 10px;
        background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.16);
        backdrop-filter: blur(4px);
    }
    .cd .tile b { display: block; font-size: 1.7rem; font-weight: 800; line-height: 1; }
    .cd .tile span { display: block; margin-top: .35rem; font-size: .6rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.6); }
    @media (max-width: 860px) {
        body { grid-template-columns: 1fr; }
        .panel { order: 2; }
        .stage { min-height: 62vh; }
    }
</style>
</head>
<body>
<aside class="panel">
    <div class="mark">
        <?php if ($brand['logo'] !== '') : ?>
            <img src="<?php echo esc_url($brand['logo']); ?>" alt="<?php echo esc_attr($site); ?>">
        <?php else : ?>
            <?php echo esc_html($site); ?>
        <?php endif; ?>
    </div>

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
</aside>

<main class="stage">
    <div>
        <div class="eyebrow"><?php esc_html_e('We are coming soon', 'ezit-core'); ?></div>
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
    </div>
</main>
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
