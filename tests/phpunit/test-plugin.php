<?php

namespace XWP\BlockVisibilityEdgeCache\Tests;

use XWP\BlockVisibilityEdgeCache\Plugin;
use XWP\BlockVisibilityEdgeCache\Schedule_Calculator;

class Test_Plugin extends \WP_UnitTestCase {

	public function data_get_schedules_from_blocks(): array {
		return [
			'simple_block' => [
				[
					[
						'blockName'   => 'core/group',
						'attrs'       => [
							'blockVisibility' => [
								'controlSets' => [
									[
										'enable'   => true,
										'controls' => [
											'dateTime' => [
												'schedules' => [
													[
														'enable' => true,
														'start'  => '2099-12-01 10:00:00',
													],
												],
											],
										],
									],
								],
							],
						],
						'innerBlocks' => [],
					],
				],
				1,
				'2099-12-01 10:00:00',
			],
		];
	}

	/**
	 * @dataProvider data_get_schedules_from_blocks
	 */
	public function test_get_schedules_from_blocks( array $blocks, int $expected_count, ?string $expected_start_key ) {
		$schedules = Schedule_Calculator::get_schedules_from_blocks( $blocks );
		$this->assertCount( $expected_count, $schedules );
	}

	public function test_schedule_cache_purge_on_publish() {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => '<!-- wp:group {"blockVisibility":{"controlSets":[{"enable":true,"controls":{"dateTime":{"schedules":[{"enable":true,"start":"2099-06-01 10:00:00"}]}}}]}} --><div class="wp-block-group"></div><!-- /wp:group -->',
		] );

		$feature = new Plugin();
		$post    = get_post( $post_id );
		$feature->schedule_cache_purge_on_post_status_transition( 'publish', 'draft', $post );

		$actions = as_get_scheduled_actions( [
			'hook'   => 'xwp_block_visibility_purge_cache',
			'args'   => [ 'post_id' => $post_id ],
			'status' => \ActionScheduler_Store::STATUS_PENDING,
		] );

		$this->assertNotEmpty( $actions, 'Expected at least one scheduled action for cache purge.' );
	}

	public function test_unschedule_cache_purge_on_trash() {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => '<!-- wp:group {"blockVisibility":{"controlSets":[{"enable":true,"controls":{"dateTime":{"schedules":[{"enable":true,"start":"2099-06-01 10:00:00"}]}}}]}} --><div class="wp-block-group"></div><!-- /wp:group -->',
		] );

		// First schedule an action.
		$feature = new Plugin();
		$post    = get_post( $post_id );
		$feature->schedule_cache_purge_on_post_status_transition( 'publish', 'draft', $post );

		// Now unschedule via delete.
		$feature->unschedule_cache_purge_on_delete( $post_id );

		$actions = as_get_scheduled_actions( [
			'hook'   => 'xwp_block_visibility_purge_cache',
			'args'   => [ 'post_id' => $post_id ],
			'status' => \ActionScheduler_Store::STATUS_PENDING,
		] );

		$this->assertEmpty( $actions, 'Expected no scheduled actions after unschedule.' );
	}

	public function test_purge_post_cache_fires_action() {
		$post_id = self::factory()->post->create( [
			'post_status'  => 'publish',
			'post_content' => '',
		] );

		$fired = false;
		add_action( 'xwp_block_visibility_edge_cache_purged', function ( $id ) use ( &$fired, $post_id ) {
			if ( $id === $post_id ) {
				$fired = true;
			}
		} );

		$feature = new Plugin();
		$feature->purge_post_cache( $post_id );

		$this->assertTrue( $fired, 'Expected xwp_block_visibility_edge_cache_purged action to fire.' );
	}

	public function test_filter_visibility_controls() {
		$settings = [ 'visibility_controls' => [ 'browser_device' => [ 'enable' => true ] ] ];
		$feature  = new Plugin();
		$filtered = $feature->filter_visibility_controls( $settings );
		$this->assertFalse( $filtered['visibility_controls']['browser_device']['enable'] );
	}
}
