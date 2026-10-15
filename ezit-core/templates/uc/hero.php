<?php
/**
 * UC layout: Hero — full-bleed photo, wordmark top-left, headline +
 * countdown, client login panel below. (Alive/Advent style.)
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
        min-height: 100vh; display: flex; flex-direction: column;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #fff;
        background:
            linear-gradient(rgba(10,12,10,.72), rgba(10,12,10,.82)),
            <?php echo $bg; ?> center/cover no-repeat,
            #14161a radial-gradient(ellipse at top, #23262e 0%, #14161a 70%);
    }
    .topbar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.6rem clamp(1.5rem, 5vw, 4rem);
    }
    .mark { font-size: 1.35rem; font-weight: 800; letter-spacing: -.02em; text-decoration: none; color: #fff; }
    .mark img { max-height: 40px; display: block; }
    .topbar .btn {
        padding: .65rem 1.4rem; border-radius: 8px; text-decoration: none;
        background: <?php echo $a; ?>; color: #0e130c; font-weight: 700; font-size: .9rem;
    }
    .hero {
        flex: 1; display: grid; place-items: center; text-align: center;
        padding: 3rem 1.5rem;
    }
    .eyebrow {
        color: <?php echo $a; ?>; font-size: .8rem; font-weight: 700;
        letter-spacing: .3em; text-transform: uppercase; margin-bottom: 1rem;
    }
    h1 {
        font-size: clamp(2.6rem, 8vw, 5rem); font-weight: 800; letter-spacing: -.03em;
        margin-bottom: 1.2rem; text-wrap: balance;
    }
    p.msg { color: rgba(255,255,255,.72); max-width: 560px; line-height: 1.7; font-size: 1.05rem; }
    .cd { display: flex; gap: clamp(.7rem, 2.5vw, 1.5rem); justify-content: center; margin-top: 2.6rem; }
    .cd .t { min-width: 74px; }
    .cd b { display: block; font-size: clamp(1.9rem, 5vw, 3rem); font-weight: 800; line-height: 1; }
    .cd span {
        display: block; margin-top: .45rem; font-size: .68rem; letter-spacing: .16em;
        text-transform: uppercase; color: rgba(255,255,255,.55);
    }
    .access {
        background: rgba(0,0,0,.35); backdrop-filter: blur(6px);
        border-top: 1px solid rgba(255,255,255,.08);
        padding: 2.2rem 1.5rem; display: grid; place-items: center;
    }
    .access form { width: min(420px, 100%); }
    .access h2 {
        text-align: center; font-size: .8rem; letter-spacing: .18em; text-transform: uppercase;
        color: rgba(255,255,255,.5); margin-bottom: 1rem; font-weight: 600;
    }
    .access .row { display: flex; gap: .6rem; }
    .access input {
        flex: 1; min-width: 0; padding: .75rem .9rem; border-radius: 8px;
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.16);
        color: #fff; font-size: .95rem;
    }
    .access input::placeholder { color: rgba(255,255,255,.4); }
    .access input:focus { outline: none; border-color: <?php echo $a; ?>; }
    .access button {
        padding: .75rem 1.5rem; border: 0; border-radius: 8px; cursor: pointer;
        background: <?php echo $a; ?>; color: #0e130c; font-weight: 700; font-size: .95rem;
    }
    .access .owner { font-size: .75rem; color: rgba(255,255,255,.4); margin: .9rem 0 0; text-align: center; }
    .access .owner a { color: rgba(255,255,255,.6); }
    .err {
        background: #3a1414; border: 1px solid #7a2b2b; color: #f0b8b8;
        padding: .6rem .8rem; border-radius: 8px; font-size: .85rem; margin-bottom: .8rem;
    }
    .foot { text-align: center; padding: 1.2rem; font-size: .75rem; color: rgba(255,255,255,.35); }
    @media (max-width: 560px) { .access .row { flex-direction: column; } }
</style>
</head>
<body>
<header class="topbar">
    <a class="mark" href="<?php echo esc_url(home_url('/')); ?>">
        <?php if ($brand['logo'] !== '') : ?>
            <img src="<?php echo esc_url($brand['logo']); ?>" alt="<?php echo esc_attr($site); ?>">
        <?php else : ?>
            <?php echo esc_html($site); ?><span style="color:<?php echo $a; ?>">.</span>
        <?php endif; ?>
    </a>
    <a class="btn" href="#access"><?php esc_html_e('Client login', 'ezit-core'); ?></a>
</header>

<main class="hero">
    <div>
        <div class="eyebrow"><?php esc_html_e('Our website is coming soon', 'ezit-core'); ?></div>
        <h1><?php echo esc_html($title); ?></h1>
        <p class="msg"><?php echo nl2br(esc_html($message)); ?></p>

        <?php if ($brand['countdown'] !== '') : ?>
        <div class="cd" data-until="<?php echo esc_attr($brand['countdown']); ?>">
            <div class="t"><b data-u="d">00</b><span><?php esc_html_e('Days', 'ezit-core'); ?></span></div>
            <div class="t"><b data-u="h">00</b><span><?php esc_html_e('Hours', 'ezit-core'); ?></span></div>
            <div class="t"><b data-u="m">00</b><span><?php esc_html_e('Minutes', 'ezit-core'); ?></span></div>
            <div class="t"><b data-u="s">00</b><span><?php esc_html_e('Seconds', 'ezit-core'); ?></span></div>
        </div>
        <?php endif; ?>
    </div>
</main>

<section class="access" id="access">
    <form method="post" action="<?php echo esc_attr($post_to); ?>">
        <h2><?php esc_html_e('Client access', 'ezit-core'); ?></h2>
        <?php if ($err !== '') : ?>
            <div class="err"><?php echo esc_html($err); ?></div>
        <?php endif; ?>
        <input type="hidden" name="ezit_gate_login" value="1">
        <div class="row">
            <input name="ezit_gate_user" type="text" placeholder="<?php esc_attr_e('Username', 'ezit-core'); ?>" autocomplete="username" required>
            <input name="ezit_gate_pass" type="password" placeholder="<?php esc_attr_e('Password', 'ezit-core'); ?>" autocomplete="current-password" required>
            <button type="submit"><?php esc_html_e('Log in', 'ezit-core'); ?></button>
        </div>
    </form>
</section>

<p class="foot"><?php echo esc_html($site); ?> — <?php esc_html_e('powered by', 'ezit-core'); ?> <a href="http://www.Ez-IT-Solutions.com" target="_blank" rel="noopener" style="color:inherit">Ez IT Solutions</a></p>

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
