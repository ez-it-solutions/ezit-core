=== Ez IT Core ===
Contributors: ezitsolutions
Tags: licensing, marketplace, updates, theme framework
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPL-2.0-or-later

Shared foundation for Ez IT Solutions WordPress themes: licensing, updates,
marketplace and support — backed by license.ez-it-solutions.com.

== Description ==

Ez IT Core is installed once per site alongside any Ez IT theme. Themes
register themselves via `ezit_core()->register_product()` and immediately
gain license activation, WP-native update notices, and the Ez IT
Marketplace — all backed by our self-hosted LicenseBox-compatible
activation server.

== Installation ==

1. Upload `ezit-core` to `/wp-content/plugins/` and activate.
2. Install an Ez IT theme (e.g. Trezise Insurance).
3. Ez IT → Licenses → paste the license code issued in the license
   dashboard → Activate.
4. Ez IT → Settings only if pointing at a non-default server.
