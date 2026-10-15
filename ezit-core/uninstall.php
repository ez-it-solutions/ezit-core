<?php
/**
 * Ez IT Core — uninstall. Drops stored license certs and settings so a
 * re-install starts clean. Product registry is runtime-only, nothing to
 * remove there.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('ezit_licenses');
delete_option('ezit_api_url');

wp_clear_scheduled_hook('ezit_daily_license_verify');
