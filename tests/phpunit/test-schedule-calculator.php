<?php
/**
 * Class Test_Schedule_Calculator
 *
 * @package XWP\BlockVisibilityEdgeCache
 */

namespace XWP\BlockVisibilityEdgeCache\Tests;

use XWP\BlockVisibilityEdgeCache\Schedule_Calculator;
use DateTimeImmutable;
use DateTimeZone;

class Test_Schedule_Calculator extends \WP_UnitTestCase {

	public function test_basic_future_timestamp() {
		$now = new DateTimeImmutable( '2025-01-01 12:00:00', new DateTimeZone( 'UTC' ) );
		
		$schedules = [
			[
				'enable' => true,
				'start' => '2025-01-02 12:00:00', // Future
				'end' => '2025-01-01 10:00:00', // Past
			]
		];

		$next = Schedule_Calculator::get_next_future_timestamp( $schedules, $now );
		
		$this->assertEquals( ( new DateTimeImmutable( '2025-01-02 12:00:00', new DateTimeZone( 'UTC' ) ) )->getTimestamp(), $next );
	}

	public function test_seasonal_schedule() {
		$now = new DateTimeImmutable( '2025-12-01 12:00:00', new DateTimeZone( 'UTC' ) );
		
		// Seasonal event on Jan 1st.
		$schedules = [
			[
				'enable' => true,
				'isSeasonal' => true,
				'start' => '2020-01-01 12:00:00', // Year doesn't matter for seasonal extraction logic if implemented correctly
			]
		];

		// Should point to 2026-01-01 since 2025-01-01 is past relative to Dec 2025.
		$next = Schedule_Calculator::get_next_future_timestamp( $schedules, $now );
		
		$this->assertEquals( ( new DateTimeImmutable( '2026-01-01 12:00:00', new DateTimeZone( 'UTC' ) ) )->getTimestamp(), $next );
	}

	public function test_day_of_week_schedule() {
		// Monday
		$now = new DateTimeImmutable( '2025-01-06 12:00:00', new DateTimeZone( 'UTC' ) ); // Jan 6 2025 is Monday
		
		$schedules = [
			[
				'enable' => true,
				'dayOfWeek' => [
					'enable' => true,
					'days' => ['Mon', 'Wed'] // Enable Mon, Wed.
				]
			]
		];

		// Currently Monday (Enabled). Next transition is Tuesday 00:00 (Disabled).
		$next = Schedule_Calculator::get_next_future_timestamp( $schedules, $now );
		$expected = ( new DateTimeImmutable( '2025-01-07 00:00:00', new DateTimeZone( 'UTC' ) ) )->getTimestamp();
		
		$this->assertEquals( $expected, $next );
	}

	public function test_time_of_day_schedule() {
		$now = new DateTimeImmutable( '2025-01-01 10:00:00', new DateTimeZone( 'UTC' ) );
		
		$schedules = [
			[
				'enable' => true,
				'timeOfDay' => [
					'enable' => true,
					'intervals' => [
						[ 'start' => '09:00:00', 'end' => '17:00:00' ]
					]
				]
			]
		];

		// 9am passed. Next is 5pm (17:00).
		$next = Schedule_Calculator::get_next_future_timestamp( $schedules, $now );
		$expected = ( new DateTimeImmutable( '2025-01-01 17:00:00', new DateTimeZone( 'UTC' ) ) )->getTimestamp();
		
		$this->assertEquals( $expected, $next );
	}
}
