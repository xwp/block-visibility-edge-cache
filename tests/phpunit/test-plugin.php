<?php

namespace XWP\BlockVisibilityEdgeCache\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use XWP\BlockVisibilityEdgeCache\Plugin;
use XWP\BlockVisibilityEdgeCache\Schedule_Calculator;
use WP_Post;

class Test_Plugin extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

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
														'start'  => '2025-12-01 10:00:00',
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
				'2025-12-01 10:00:00',
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
		$post_id = 123;
		Functions\expect( 'get_post' )->andReturn( new WP_Post( [
			'ID'           => $post_id,
			'post_status'  => 'publish',
			'post_content' => ''
		] ) );
		Functions\expect( 'parse_blocks' )->andReturn( [] );
		Functions\expect( 'as_unschedule_all_actions' )->once();
		$feature = new Plugin();
		$feature->schedule_cache_purge_on_post_status_transition( 'publish', 'draft', new WP_Post( [ 'ID' => $post_id ] ) );
		$this->assertTrue( true );
	}

	public function test_unschedule_cache_purge_on_trash() {
		$post_id = 123;
		Functions\expect( 'as_unschedule_all_actions' )->once();
		$feature = new Plugin();
		$feature->unschedule_cache_purge_on_delete( $post_id );
		$this->assertTrue( true );
	}

	public function test_purge_post_cache_chains_to_next_timestamp() {
		$post_id = 123;
		Functions\expect( 'wpcom_vip_purge_edge_cache_for_post' )->once();
		Functions\expect( 'do_action' )->once();
		Functions\expect( 'get_post' )->andReturn( new WP_Post( [
			'ID'           => $post_id,
			'post_status'  => 'publish',
			'post_content' => ''
		] ) );
		Functions\expect( 'parse_blocks' )->andReturn( [] );
		$feature = new Plugin();
		$feature->purge_post_cache( $post_id );
		$this->assertTrue( true );
	}

	public function test_filter_visibility_controls() {
		$settings = [ 'visibility_controls' => [ 'browser_device' => [ 'enable' => true ] ] ];
		$feature  = new Plugin();
		$filtered = $feature->filter_visibility_controls( $settings );
		$this->assertFalse( $filtered['visibility_controls']['browser_device']['enable'] );
	}
}
