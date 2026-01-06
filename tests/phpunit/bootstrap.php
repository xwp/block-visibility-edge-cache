<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package XWP\BlockVisibilityEdgeCache
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Initialize Brain\Monkey if available.
if ( class_exists( 'Brain\Monkey\Engine' ) ) {
	Brain\Monkey::preload();
}

// Define dummy WP_Post class if not exists, to satisfy type hints.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public $ID;
		public $post_status;
		public $post_content;
		public function __construct( $data = [] ) {
			foreach ( $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}



