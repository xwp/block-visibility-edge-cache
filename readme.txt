=== Block Visibility Edge Cache ===
Contributors: xwp
Tags: block-visibility, cache, vip, action-scheduler
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.4
Requires Plugins: block-visibility
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

This plugin provides edge cache invalidation for posts containing time-based block visibility schedules from the Block Visibility plugin. It ensures that blocks scheduled to appear or disappear at specific times are reflected immediately on the edge cache (specifically for VIP platform) by scheduling proactive cache purges using Action Scheduler.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure the 'Block Visibility' plugin is also active.
4. Ensure Action Scheduler is available (usually bundled with WooCommerce or as a standalone plugin).

== Frequently Asked Questions ==

= Does this require the Block Visibility plugin? =
Yes, it extracts visibility settings from blocks managed by the Block Visibility plugin.

= Which platforms are supported? =
Currently, it has built-in support for the VIP platform's `wpcom_vip_purge_edge_cache_for_post` function. Other platforms can hook into `xwp_block_visibility_edge_cache_purged`.

== Changelog ==

= 1.0.0 =
* Initial release. Extract logic from theme into standalone plugin.
