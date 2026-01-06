<?php
/**
 * Schedule Calculator class.
 *
 * @package XWP\BlockVisibilityEdgeCache
 */

namespace XWP\BlockVisibilityEdgeCache;

use DateTimeImmutable;
use Exception;

/**
 * Class Schedule_Calculator
 * 
 * Handles parsing blocks and calculating future timestamps for cache invalidation.
 */
class Schedule_Calculator {

	/**
	 * Extract schedules from a list of blocks recursively.
	 *
	 * @param array<array<string, mixed>> $blocks Parsed block array.
	 * @return array<array<string, mixed>> List of found schedules.
	 */
	public static function get_schedules_from_blocks( array $blocks ): array {
		$schedules = [];

		foreach ( $blocks as $block ) {
			// Check for block visibility settings in attrs.
			$attrs = $block['attrs'] ?? null;
			if ( is_array( $attrs ) ) {
				$visibility = $attrs['blockVisibility'] ?? null;
				if ( is_array( $visibility ) ) {
					$control_sets = $visibility['controlSets'] ?? null;
					if ( is_array( $control_sets ) ) {
						foreach ( $control_sets as $control_set ) {
							if ( ! is_array( $control_set ) ) {
								continue;
							}

							// Ensure control set is enabled.
							if ( empty( $control_set['enable'] ) ) {
								continue;
							}

							// Check for Date/Time controls.
							$controls = $control_set['controls'] ?? null;
							if ( is_array( $controls ) ) {
								$date_time = $controls['dateTime'] ?? null;
								if ( is_array( $date_time ) ) {
									$schedules_list = $date_time['schedules'] ?? null;
									if ( is_array( $schedules_list ) ) {
										foreach ( $schedules_list as $schedule ) {
											if ( is_array( $schedule ) && ! empty( $schedule['enable'] ) ) {
												$schedules[] = $schedule;
											}
										}
									}
								}
							}
						}
					}
				}
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				/** @var array<array<string, mixed>> $inner_blocks */
				$inner_blocks = $block['innerBlocks'];
				$schedules    = array_merge( $schedules, self::get_schedules_from_blocks( $inner_blocks ) );
			}
		}

		return $schedules;
	}

	/**
	 * Get the next future timestamp for cache invalidation based on schedules.
	 *
	 * @param array<array<string, mixed>> $schedules List of schedule arrays.
	 * @param DateTimeImmutable           $now       Reference time (usually 'now').
	 * @return int|null The next future timestamp, or null if none exist.
	 */
	public static function get_next_future_timestamp( array $schedules, DateTimeImmutable $now ): ?int {
		$timestamps = self::calculate_future_timestamps( $schedules, $now );

		return ! empty( $timestamps ) ? $timestamps[0] : null;
	}

	/**
	 * Calculate all future timestamps for cache invalidation based on schedules.
	 *
	 * A single schedule can have multiple conditions combined (date range + dayOfWeek + timeOfDay).
	 * We collect timestamps from ALL enabled conditions to ensure cache is purged at each transition.
	 *
	 * @param array<array<string, mixed>> $schedules List of schedule arrays.
	 * @param DateTimeImmutable           $now       Reference time (usually 'now').
	 * @return array<int> Unique list of future timestamps (integers), sorted ascending.
	 */
	public static function calculate_future_timestamps( array $schedules, DateTimeImmutable $now ): array {
		$timestamps   = [];
		$current_year = (int) $now->format( 'Y' );

		foreach ( $schedules as $schedule ) {
			// Collect timestamps from ALL enabled conditions in this schedule.
			// A schedule can combine date range + dayOfWeek + timeOfDay.

			// Handle timeOfDay if enabled.
			$time_of_day = $schedule['timeOfDay'] ?? null;
			if (
				is_array( $time_of_day ) &&
				! empty( $time_of_day['enable'] ) &&
				! empty( $time_of_day['intervals'] ) &&
				is_array( $time_of_day['intervals'] )
			) {
				/** @var array<array<string, string>> $intervals */
				$intervals             = $time_of_day['intervals'];
				$time_of_day_timestamp = self::calculate_time_of_day_next_transition( $intervals, $now );
				if ( null !== $time_of_day_timestamp ) {
					$timestamps[] = $time_of_day_timestamp;
				}
			}

			// Handle dayOfWeek if enabled.
			$day_of_week = $schedule['dayOfWeek'] ?? null;
			if (
				is_array( $day_of_week ) &&
				! empty( $day_of_week['enable'] ) &&
				! empty( $day_of_week['days'] ) &&
				is_array( $day_of_week['days'] )
			) {
				/** @var array<string> $days */
				$days                  = $day_of_week['days'];
				$day_of_week_timestamp = self::calculate_day_of_week_next_transition( $days, $now );
				if ( null !== $day_of_week_timestamp ) {
					$timestamps[] = $day_of_week_timestamp;
				}
			}

			// Handle date-based schedules (start/end dates).
			$start_str   = isset( $schedule['start'] ) && is_string( $schedule['start'] ) ? $schedule['start'] : null;
			$end_str     = isset( $schedule['end'] ) && is_string( $schedule['end'] ) ? $schedule['end'] : null;
			$is_seasonal = ! empty( $schedule['isSeasonal'] );

			$dates_to_process = [];
			if ( $start_str ) {
				$dates_to_process[] = $start_str;
			}
			if ( $end_str ) {
				$dates_to_process[] = $end_str;
			}

			foreach ( $dates_to_process as $date_str ) {
				try {
					$dt = new DateTimeImmutable( $date_str, $now->getTimezone() );
				} catch ( Exception $e ) {
					continue;
				}

				if ( $is_seasonal ) {
					$month  = (int) $dt->format( 'm' );
					$day    = (int) $dt->format( 'd' );
					$hour   = (int) $dt->format( 'H' );
					$minute = (int) $dt->format( 'i' );
					$second = (int) $dt->format( 's' );

					$dt_this_year = $now->setDate( $current_year, $month, $day )->setTime( $hour, $minute, $second );

					if ( $dt_this_year > $now ) {
						$timestamps[] = $dt_this_year->getTimestamp();
					} else {
						$dt_next_year = $dt_this_year->modify( '+1 year' );
						$timestamps[] = $dt_next_year->getTimestamp();
					}
				} elseif ( $dt > $now ) {
					$timestamps[] = $dt->getTimestamp();
				}
			}
		}

		$timestamps = array_unique( $timestamps );
		sort( $timestamps );

		return $timestamps;
	}

	/**
	 * Calculate the next transition timestamp for a dayOfWeek schedule.
	 *
	 * Determines whether the current moment is inside or outside the enabled days,
	 * then calculates when the next state change (show/hide) should occur at midnight.
	 *
	 * @param array<string>     $enabled_days Array of enabled day abbreviations (e.g., ["Mon", "Wed", "Fri"]).
	 * @param DateTimeImmutable $now          Reference time.
	 * @return int|null Timestamp of next transition (midnight), or null if no valid days.
	 */
	public static function calculate_day_of_week_next_transition( array $enabled_days, DateTimeImmutable $now ): ?int {
		if ( empty( $enabled_days ) ) {
			return null;
		}

		// Map day abbreviations to ISO-8601 numeric day (1=Mon, 7=Sun).
		$day_map = [
			'Mon' => 1,
			'Tue' => 2,
			'Wed' => 3,
			'Thu' => 4,
			'Fri' => 5,
			'Sat' => 6,
			'Sun' => 7,
		];

		// Convert enabled days to numeric values.
		$enabled_day_numbers = [];
		foreach ( $enabled_days as $day ) {
			if ( isset( $day_map[ $day ] ) ) {
				$enabled_day_numbers[] = $day_map[ $day ];
			}
		}

		if ( empty( $enabled_day_numbers ) ) {
			return null;
		}

		sort( $enabled_day_numbers );

		// Get current day of week (1=Mon, 7=Sun).
		$current_day = (int) $now->format( 'N' );

		// Determine if we're currently in an enabled day.
		$is_currently_enabled = in_array( $current_day, $enabled_day_numbers, true );

		// Find next transition:
		// - If currently enabled: find next day NOT in the list (hide transition).
		// - If currently disabled: find next day IN the list (show transition).

		// Check each day starting from tomorrow.
		for ( $i = 1; $i <= 7; $i++ ) {
			$check_day      = ( $current_day + $i - 1 ) % 7 + 1; // Wrap around week (1-7).
			$is_day_enabled = in_array( $check_day, $enabled_day_numbers, true );

			// Transition occurs when state changes.
			if ( $is_currently_enabled && ! $is_day_enabled ) {
				// We're in an enabled day, and this is the first disabled day = hide transition.
				$next_midnight = $now->modify( "+{$i} days" )->setTime( 0, 0, 0 );
				return $next_midnight->getTimestamp();
			} elseif ( ! $is_currently_enabled && $is_day_enabled ) {
				// We're in a disabled day, and this is the first enabled day = show transition.
				$next_midnight = $now->modify( "+{$i} days" )->setTime( 0, 0, 0 );
				return $next_midnight->getTimestamp();
			}
		}

		// All 7 days are either all enabled or all disabled (edge case).
		// No transitions needed - return null.
		return null;
	}

	/**
	 * Calculate the next transition timestamp for a timeOfDay schedule.
	 *
	 * Finds the next start or end time from the intervals that is in the future,
	 * checking today first and then tomorrow if needed.
	 *
	 * @param array<array<string, string>> $intervals Array of time intervals with start/end times (e.g., [["start" => "09:00:00", "end" => "17:00:00"]]).
	 * @param DateTimeImmutable            $now       Reference time.
	 * @return int|null Timestamp of next transition, or null if no valid intervals.
	 */
	public static function calculate_time_of_day_next_transition( array $intervals, DateTimeImmutable $now ): ?int {
		if ( empty( $intervals ) ) {
			return null;
		}

		$all_transitions = [];

		// Check today and tomorrow for transition times.
		for ( $day_offset = 0; $day_offset <= 1; $day_offset++ ) {
			$check_date = $now->modify( "+{$day_offset} days" )->setTime( 0, 0, 0 );

			foreach ( $intervals as $interval ) {
				$start_time_str = $interval['start'] ?? null;
				$end_time_str   = $interval['end'] ?? null;

				// Parse and add start time if valid.
				if ( ! empty( $start_time_str ) ) {
					$start_parts = explode( ':', $start_time_str );
					if ( count( $start_parts ) >= 2 ) {
						$start_dt = $check_date->setTime(
							(int) $start_parts[0],
							(int) $start_parts[1],
							isset( $start_parts[2] ) ? (int) $start_parts[2] : 0
						);
						if ( $start_dt > $now ) {
							$all_transitions[] = $start_dt->getTimestamp();
						}
					}
				}

				// Parse and add end time if valid.
				if ( ! empty( $end_time_str ) ) {
					$end_parts = explode( ':', $end_time_str );
					if ( count( $end_parts ) >= 2 ) {
						$end_dt = $check_date->setTime(
							(int) $end_parts[0],
							(int) $end_parts[1],
							isset( $end_parts[2] ) ? (int) $end_parts[2] : 0
						);
						if ( $end_dt > $now ) {
							$all_transitions[] = $end_dt->getTimestamp();
						}
					}
				}
			}
		}

		if ( empty( $all_transitions ) ) {
			return null;
		}

		// Return the earliest future transition.
		sort( $all_transitions );
		return $all_transitions[0];
	}
}
