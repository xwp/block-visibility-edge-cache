<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package XWP\BlockVisibilityEdgeCache
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/wordpress-phpunit/';

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require_once '/var/www/html/wp-content/plugins/block-visibility/block-visibility.php';
		require_once dirname( __DIR__, 2 ) . '/block-visibility-edge-cache.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
