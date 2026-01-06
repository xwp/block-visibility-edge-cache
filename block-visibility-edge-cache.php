<?php
/**
 * Plugin Name: Block Visibility Edge Cache
 * Description: Handles edge cache invalidation for blocks with time-based visibility settings.
 * Version: 1.0.0
 * Author: XWP
 * Text Domain: block-visibility-edge-cache
 * Requires PHP: 8.4
 * Requires Plugins: block-visibility
 *
 * @package XWP\BlockVisibilityEdgeCache
 */

namespace XWP\BlockVisibilityEdgeCache;

// Autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Load Action Scheduler if not already loaded.
if ( ! class_exists( 'ActionScheduler_Store' ) ) {
	if ( file_exists( __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
		require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', function () {
	( new Plugin() )->init();
} );
