<?php
/**
 * UC layout: Bold — dark page, accent countdown tiles, inline login bar.
 * (City-skyline screenshot style.)
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
        min-height: 100vh; display: grid; place-items: center; padding: 2.5rem 1.5rem;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #fff;
        background:
            linear-gradient(rgba(12,12,14,.78), rgba(12,12,14,.88)),
            <?php echo $bg; ?> center/cover no-repeat,
            #101013 radial-gradient(ellipse at center, #1c1c22 0%, #101013 75%);
        text-align: center;
    }
    .wrap { max-width: 720px; width: 100%; }
    .mark img { max-height: 44px; margin-bottom: 1.6rem; }
    h1 { font-size: clamp(2rem, 6vw, 3.4rem); font-weight: 800; letter-spacing: -.02em; margin-bottom: 1rem; text-wrap: balance; }
    p.msg { color: rgba(255,255,255,.62); line-height: 1.7; max-width: 520px; margin: 0 auto 2.4rem; }
    .cd { display: flex; gap: .9rem; justify-content: center; align-items: center; margin-bottom: 2.6rem; flex-wrap: wrap; }
    .cd .tile {
        min-width: 92px; padding: 1.1rem .5rem .9rem; border-radius: 10px;
        background: <?php echo $a; ?>; color: #0e130c;
    }
    .cd .tile b { display: block; font-size: 2rem; font-weight: 800; line-height: 1; }
    .cd .tile span { display: block; margin-top: .4rem; font-size: .62rem; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; opacity: .75; }
    .cd .sep { font-size: 2rem; font-weight: 800; color: rgba(255,255,255,.3); }
    .access { max-width: 520px; margin: 0 auto; }
    .access h2 {
        font-size: .78rem; letter-spacing: .2em; text-transform: uppercase;
        color: rgba(255,255,255,.45); margin-bottom: 1rem; font-weight: 600;
    }
    .access .bar { display: flex; border-radius: 10px; overflow: hidden; background: rgba(255,255,255,.92); }
    .access input {
        flex: 1; min-width: 0; padding: .9rem 1rem; border: 0; background: transparent;
        color: #16161a; font-size: .95rem;
    }
    .access input + input { border-left: 1px solid rgba(0,0,0,.08); }
    .access input:focus { outline: none; background: #fff; }
    .access button {
        padding: 0 1.6rem; border: 0; cursor: pointer; font-weight: 700; font-size: .95rem;
        background: <?php echo $a; ?>; color: #0e130c;
    }
    .access .owner { font-size: .75rem; color: rgba(255,255,255,.4); margin: .9rem 0 0; }
    .access .owner a { color: rgba(255,255,255,.6); }
    .err {
        background: #3a1414; border: 1px solid #7a2b2b; color: #f0b8b8;
        padding: .6rem .8rem; border-radius: 8px; font-size: .85rem; margin-bottom: .8rem; text-align: left;
    }
    .foot { margin-top: 3rem; font-size: .75rem; color: rgba(255,255,255,.3); }
    @media (max-width: 520px) { .access .bar { flex-direction: column; } .access input + input { border-left: 0; border-top: 1px solid rgba(0,0,0,.08); } .access button { padding: .9rem; } }
</style>
</head>
<body>
<div class="wrap">
    <?php if ($brand['logo'] !== '') : ?>
        <div class="mark"><img src="<?php echo esc_url($brand['logo']); ?>" alt="<?php echo esc_attr($site); ?>"></div>
    <?php endif; ?>
    <h1><?php echo esc_html($title); ?></h1>
    <p class="msg"><?php echo nl2br(esc_html($message)); ?></p>

    <?php if ($brand['countdown'] !== '') : ?>
    <div class="cd" data-until="<?php echo esc_attr($brand['countdown']); ?>">
        <div class="tile"><b data-u="d">00</b><span><?php esc_html_e('Days', 'ezit-core'); ?></span></div>
        <div class="sep">:</div>
        <div class="tile"><b data-u="h">00</b><span><?php esc_html_e('Hours', 'ezit-core'); ?></span></div>
        <div class="sep">:</div>
        <div class="tile"><b data-u="m">00</b><span><?php esc_html_e('Minutes', 'ezit-core'); ?></span></div>
        <div class="sep">:</div>
        <div class="tile"><b data-u="s">00</b><span><?php esc_html_e('Seconds', 'ezit-core'); ?></span></div>
    </div>
    <?php endif; ?>

    <div class="access">
        <h2><?php esc_html_e('Client access', 'ezit-core'); ?></h2>
        <?php if ($err !== '') : ?>
            <div class="err"><?php echo esc_html($err); ?></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_attr($post_to); ?>">
            <input type="hidden" name="ezit_gate_login" value="1">
            <div class="bar">
                <input name="ezit_gate_user" type="text" placeholder="<?php esc_attr_e('Username', 'ezit-core'); ?>" autocomplete="username" required>
                <input name="ezit_gate_pass" type="password" placeholder="<?php esc_attr_e('Password', 'ezit-core'); ?>" autocomplete="current-password" required>
                <button type="submit"><?php esc_html_e('Log in', 'ezit-core'); ?></button>
            </div>
        </form>
    </div>

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
