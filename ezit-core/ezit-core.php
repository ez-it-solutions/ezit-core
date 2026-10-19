<?php
/**
 * Plugin Name: Ez IT Core
 * Plugin URI:  https://ez-it-solutions.com
 * Description: Shared foundation for every Ez IT Solutions theme — licensing,
 *              marketplace, updates and support, backed by the Ez IT license
 *              server (license.ez-it-solutions.com, LicenseBox-compatible).
 * Version:     1.4.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author:      Ez IT Solutions
 * Author URI:  https://ez-it-solutions.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ezit-core
 */

if (! defined('ABSPATH')) {
    exit;
}

define('EZIT_CORE_VERSION', '1.4.1');
define('EZIT_CORE_FILE', __FILE__);
define('EZIT_CORE_PATH', plugin_dir_path(__FILE__));
define('EZIT_CORE_INC',  EZIT_CORE_PATH . 'inc/');
define('EZIT_CORE_URL',  plugin_dir_url(__FILE__));
define('EZIT_CORE_ASSETS', EZIT_CORE_URL . 'assets/');

// Default activation-server base; per-site override in wp-config.php or
// Settings → Ez IT Core. No trailing slash.
if (! defined('EZIT_API_URL')) {
    define('EZIT_API_URL', 'https://license.ez-it-solutions.com');
}

// EzIT Core is its own product on the license server — the same zip-drop
// pipeline ships plugin updates to every site (including forced ones).
// Fill in once the product exists (license dashboard → Products → Edit →
// API key). Blank = self-update off; everything else keeps working.
// Overridable in wp-config.php per site.
if (! defined('EZIT_CORE_PRODUCT_ID')) {
    define('EZIT_CORE_PRODUCT_ID', '');
}
if (! defined('EZIT_CORE_PRODUCT_KEY')) {
    define('EZIT_CORE_PRODUCT_KEY', '');
}

// Load every module in inc/ — sorted for determinism. Modules only
// reference each other at runtime (not at include time), so order
// doesn't matter. Auto-scanning means a new module file can't be
// orphaned by an out-of-date require list.
foreach (glob(EZIT_CORE_INC . 'class-ezit-*.php') ?: [] as $module) {
    require_once $module;
}

/**
 * Global access point: ezit_core()->register_product([...])
 */
function ezit_core(): EzIT_Core
{
    return EzIT_Core::instance();
}

add_action('plugins_loaded', function () {
    EzIT_Themes::boot(); // exposes the `EzIT Product` product header
    $core = ezit_core();

    // Self-update: register EzIT Core as a 'plugin' product so the
    // updater feeds it into update_plugins and can force-apply flagged
    // releases. 'self' → check/download authenticate by api_key alone
    // (no license cert — the server's free-catalog path covers it).
    if (EZIT_CORE_PRODUCT_ID !== '' && EZIT_CORE_PRODUCT_KEY !== '') {
        $core->register_product([
            'slug'        => 'ezit-core',
            'type'        => 'plugin',
            'name'        => 'Ez IT Core',
            'version'     => EZIT_CORE_VERSION,
            'product_id'  => EZIT_CORE_PRODUCT_ID,
            'api_key'     => EZIT_CORE_PRODUCT_KEY,
            'gated'       => false,
            'marketplace' => false,
            'self'        => true,
        ]);
    }

    $core->licensing(); // boots the shared licensing module + cron
    $core->updater();   // shared instance — WP filters + admin tabs
    new EzIT_Marketplace($core);
    new EzIT_Gate($core);     // maintenance mode — front-end enforcement
    if (class_exists('EzIT_Sso')) {
        new EzIT_Sso($core);  // license-server → wp-admin one-time logins
    }
    // Optional modules are class_exists-guarded: a partial upload that
    // leaves a require missing must never white-screen the site.
    if (class_exists('EzIT_Login')) {
        new EzIT_Login();       // branded wp-login.php
    }
    if (class_exists('EzIT_Customizer')) {
        new EzIT_Customizer();  // "Ez IT Solutions" panel in the Customizer
    }
    if (is_admin()) {
        if (class_exists('EzIT_Admin')) {
            new EzIT_Admin($core);
        }
        if (class_exists('EzIT_Banners')) {
            new EzIT_Banners($core); // server announcements for site admins
        }
    }
}, 5);

// Theme functions.php files load AFTER plugins_loaded — register_product()
// may be called top-level there (the registry accepts it), or hook here.
add_action('after_setup_theme', function () {
    do_action('ezit_core_ready', ezit_core());
}, 99);
