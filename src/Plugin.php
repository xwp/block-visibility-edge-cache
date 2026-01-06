<?php
/**
 * Main Plugin class.
 *
 * @package XWP\BlockVisibilityEdgeCache
 */

namespace XWP\BlockVisibilityEdgeCache;

use DateTimeImmutable;
use WP_Post;

/**
 * Class Plugin
 *
 * Orchestrates cache invalidation for blocks and settings filtering.
 */
class Plugin {

	/**
	 * Controls to disable in the Block Visibility plugin.
	 *
	 * @var array<string>
	 */
	private const DISABLED_CONTROLS = [
		'browser_device',
		'cookie',
		'hide_block',
		'location',
		'metadata',
		'query_string',
		'referral_source',
		'screen_size',
		'url_path',
		'visibility_by_role',
		'visibility_presets',
		'edd',
		'woocommerce',
		'wp_fusion',
		'acf',
	];

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'transition_post_status', [ $this, 'schedule_cache_purge_on_post_status_transition' ], 10, 3 );
		add_action( 'xwp_block_visibility_purge_cache', [ $this, 'purge_post_cache' ] );
		
		add_action( 'before_delete_post', [ $this, 'unschedule_cache_purge_on_delete' ] );
		add_filter( 'block_visibility_settings_defaults', [ $this, 'filter_visibility_control_defaults' ], 99 );
		// Filter the actual option value to enforce disabled controls regardless of saved settings.
		add_filter( 'option_block_visibility_settings', [ $this, 'filter_visibility_controls' ], 99 );
	}

	/**
	 * Filter Block Visibility plugin settings defaults to disable unnecessary controls.
	 *
	 * Keeps only date_time enabled.
	 *
	 * @param array<string, mixed> $defaults The default settings array.
	 * @return array<string, mixed> Modified defaults with disabled controls.
	 */
	public function filter_visibility_control_defaults( array $defaults ): array {
		if ( ! isset( $defaults['visibility_controls'] ) || ! is_array( $defaults['visibility_controls'] ) ) {
			return $defaults;
		}

		foreach ( self::DISABLED_CONTROLS as $control ) {
			if ( isset( $defaults['visibility_controls'][ $control ] ) && is_array( $defaults['visibility_controls'][ $control ] ) ) {
				$defaults['visibility_controls'][ $control ]['enable'] = false;
			}
		}

		return $defaults;
	}

	/**
	 * Filter Block Visibility plugin saved settings to enforce disabled controls.
	 *
	 * This ensures controls remain disabled even if previously enabled in the database.
	 *
	 * Both dayOfWeek and timeOfDay are supported via scheduled cache invalidation.
	 *
	 * @param mixed $settings The saved settings value.
	 * @return mixed Modified settings with disabled controls.
	 */
	public function filter_visibility_controls( $settings ) {
		if ( ! is_array( $settings ) || ! isset( $settings['visibility_controls'] ) || ! is_array( $settings['visibility_controls'] ) ) {
			return $settings;
		}

		foreach ( self::DISABLED_CONTROLS as $control ) {
			if ( isset( $settings['visibility_controls'][ $control ] ) && is_array( $settings['visibility_controls'][ $control ] ) ) {
				$settings['visibility_controls'][ $control ]['enable'] = false;
			}
		}

		return $settings;
	}

	/**
	 * Schedules the next cache purge action when a post status transitions.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function schedule_cache_purge_on_post_status_transition( string $new_status, string $old_status, WP_Post $post ): void {
		// Only proceed if Action Scheduler is available.
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		// Always clear existing scheduled actions for this post to avoid duplicates or stale schedules.
		as_unschedule_all_actions( 'xwp_block_visibility_purge_cache', [ 'post_id' => $post->ID ], 'block_visibility_cache' );

		// Only schedule new actions if the post is published.
		if ( 'publish' !== $new_status ) {
			return;
		}

		$this->schedule_next_cache_purge( $post->ID );
	}

	/**
	 * Schedules the next cache purge action for a post if there are future timestamps.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	protected function schedule_next_cache_purge( int $post_id ): void {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$blocks    = parse_blocks( $post->post_content );
		$schedules = Schedule_Calculator::get_schedules_from_blocks( $blocks );

		if ( empty( $schedules ) ) {
			return;
		}

		$now            = new DateTimeImmutable( 'now', wp_timezone() );
		$next_timestamp = Schedule_Calculator::get_next_future_timestamp( $schedules, $now );

		if ( null !== $next_timestamp ) {
			as_schedule_single_action(
				$next_timestamp,
				'xwp_block_visibility_purge_cache',
				[ 'post_id' => $post_id ],
				'block_visibility_cache'
			);
		}
	}

	/**
	 * Purges cache for a given post and schedules the next cache purge if needed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function purge_post_cache( int $post_id ): void {
		// Purge the cache if VIP function exists.
		if ( function_exists( 'wpcom_vip_purge_edge_cache_for_post' ) ) {
			wpcom_vip_purge_edge_cache_for_post( $post_id );
		}
		
		// Also allow other plugins to hook in here.
		do_action( 'xwp_block_visibility_edge_cache_purged', $post_id );

		// Schedule the next cache purge action (chain to next timestamp).
		$this->schedule_next_cache_purge( $post_id );
	}

	/**
	 * Unschedules all cache purge actions when a post is permanently deleted.
	 *
	 * @param int $post_id Post ID being deleted.
	 * @return void
	 */
	public function unschedule_cache_purge_on_delete( int $post_id ): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( 'xwp_block_visibility_purge_cache', [ 'post_id' => $post_id ], 'block_visibility_cache' );
	}
}
