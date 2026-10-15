<?php
/**
 * UC layout: Minimal — Ez IT dark card.
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
        min-height: 100vh; display: grid; place-items: center; padding: 2rem;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background: #0e130c radial-gradient(ellipse at top, #1a2415 0%, #0e130c 65%);
        color: #e8f0e3;
    }
    .card { max-width: 460px; width: 100%; text-align: center; }
    .badge-uc {
        display: inline-block; padding: .3rem .9rem; border-radius: 99px; font-size: .75rem;
        letter-spacing: .12em; text-transform: uppercase; font-weight: 700;
        background: color-mix(in srgb, <?php echo $a; ?> 14%, transparent); color: <?php echo $a; ?>;
        border: 1px solid color-mix(in srgb, <?php echo $a; ?> 35%, transparent);
        margin-bottom: 1.4rem;
    }
    h1 { font-size: 2rem; font-weight: 750; margin-bottom: .8rem; }
    p.msg { color: #a9b6a2; line-height: 1.6; margin-bottom: 2rem; }
    .login {
        border-top: 1px solid rgba(255,255,255,.08); padding-top: 1.6rem;
        text-align: left;
    }
    .login h2 { font-size: .85rem; color: #8a967f; font-weight: 600; margin-bottom: .9rem; text-transform: uppercase; letter-spacing: .08em; }
    .login input {
        width: 100%; padding: .7rem .85rem; margin-bottom: .7rem; border-radius: 8px;
        background: #151c12; border: 1px solid #2c3a26; color: #e8f0e3; font-size: .95rem;
    }
    .login input:focus { outline: none; border-color: <?php echo $a; ?>; }
    .login button {
        width: 100%; padding: .75rem; border: 0; border-radius: 8px; cursor: pointer;
        background: <?php echo $a; ?>; color: #0e130c; font-weight: 700; font-size: .95rem;
    }
    .login button:hover { filter: brightness(1.12); }
    .login .owner { font-size: .75rem; color: #8a967f; margin: .8rem 0 0; }
    .login .owner a { color: #a9b6a2; }
    .err {
        background: #3a1414; border: 1px solid #7a2b2b; color: #f0b8b8;
        padding: .6rem .8rem; border-radius: 8px; font-size: .85rem; margin-bottom: .8rem;
    }
    .foot { margin-top: 2.4rem; font-size: .75rem; color: #5c6a54; }
</style>
</head>
<body>
<div class="card">
    <div class="badge-uc"><?php esc_html_e('Under Construction', 'ezit-core'); ?></div>
    <h1><?php echo esc_html($title); ?></h1>
    <p class="msg"><?php echo nl2br(esc_html($message)); ?></p>

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
</body>
</html>
