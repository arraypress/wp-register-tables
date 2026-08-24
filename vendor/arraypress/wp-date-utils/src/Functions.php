<?php
/**
 * Global Date Helper Functions
 *
 * Provides convenient global functions for common date operations.
 * These functions are wrappers around the ArrayPress\DateUtils\Dates class.
 *
 * Functions included:
 * - current_time_utc() - Get current UTC time for database storage
 * - utc_to_local() - Convert UTC to local timezone for display
 * - local_to_utc() - Convert local to UTC for database storage
 * - format_date() - Format dates using WordPress settings
 * - date_or_empty() - Format dates with empty value handling
 * - human_date() - Get human-readable time difference
 * - is_date_expired() - Check if a date has expired
 * - days_ago() - Get days since a date
 * - get_date_range() - Get a predefined date range
 * - is_date_fresh() - Check if date is within threshold
 *
 * @package ArrayPress\DateUtils
 * @since   1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use ArrayPress\DateUtils\Dates;

/**
 * Global Date Helper Functions
 *
 * Essential shortcuts for the most common date operations.
 * These are intentionally in the global namespace for easy access.
 */

if ( ! function_exists( 'current_time_utc' ) ) {
	/**
	 * Get current UTC datetime for database storage.
	 *
	 * @param string $format PHP date format. Default MySQL format.
	 *
	 * @return string Current UTC datetime.
	 */
	function current_time_utc( string $format = 'Y-m-d H:i:s' ): string {
		return Dates::now_utc( $format );
	}
}

if ( ! function_exists( 'utc_to_local' ) ) {
	/**
	 * Convert UTC datetime to local timezone for display.
	 *
	 * @param string $utc_datetime UTC datetime from database.
	 * @param string $format       PHP date format. Empty uses WP settings.
	 *
	 * @return string Local datetime.
	 */
	function utc_to_local( string $utc_datetime, string $format = '' ): string {
		return Dates::to_local( $utc_datetime, $format );
	}
}

if ( ! function_exists( 'local_to_utc' ) ) {
	/**
	 * Convert local datetime to UTC for database storage.
	 *
	 * @param string $local_datetime Local datetime from user input.
	 * @param string $format         Output format.
	 *
	 * @return string UTC datetime.
	 */
	function local_to_utc( string $local_datetime, string $format = 'Y-m-d H:i:s' ): string {
		return Dates::to_utc( $local_datetime, $format );
	}
}

if ( ! function_exists( 'format_date' ) ) {
	/**
	 * Format UTC datetime using WordPress settings.
	 *
	 * @param string $utc_datetime UTC datetime to format.
	 * @param string $type         Format type: 'datetime', 'date', 'time'.
	 *
	 * @return string Formatted datetime.
	 */
	function format_date( string $utc_datetime, string $type = 'datetime' ): string {
		return Dates::format( $utc_datetime, $type );
	}
}

if ( ! function_exists( 'date_or_empty' ) ) {
	/**
	 * Format datetime with empty value handling.
	 *
	 * @param string|null $utc_datetime UTC datetime to format.
	 * @param string      $empty_text   Text for empty values.
	 * @param string      $format_type  Format type: 'datetime', 'date', 'time', 'human'.
	 *
	 * @return string Formatted datetime or empty text.
	 */
	function date_or_empty( ?string $utc_datetime, string $empty_text = '—', string $format_type = 'datetime' ): string {
		return Dates::format_or_empty( $utc_datetime, $empty_text, $format_type );
	}
}

if ( ! function_exists( 'human_date' ) ) {
	/**
	 * Get human-readable time difference (e.g., "2 hours ago").
	 *
	 * @param string $utc_datetime UTC datetime.
	 *
	 * @return string Human-readable time difference.
	 */
	function human_date( string $utc_datetime ): string {
		return Dates::human_diff( $utc_datetime );
	}
}

if ( ! function_exists( 'is_date_expired' ) ) {
	/**
	 * Check if a date has expired.
	 *
	 * @param string $utc_datetime UTC datetime to check.
	 * @param int    $grace_hours  Optional grace period in hours.
	 *
	 * @return bool True if expired.
	 */
	function is_date_expired( string $utc_datetime, int $grace_hours = 0 ): bool {
		return Dates::is_expired( $utc_datetime, $grace_hours );
	}
}

if ( ! function_exists( 'days_ago' ) ) {
	/**
	 * Get number of days since a date.
	 *
	 * @param string $utc_datetime UTC datetime.
	 *
	 * @return int Days since the date.
	 */
	function days_ago( string $utc_datetime ): int {
		return Dates::diff( $utc_datetime, Dates::now_utc() );
	}
}

/*
 * Named get_date_range() rather than date_range().
 *
 * Strauss prefixes global function names, and it cannot tell a function
 * reference from a string literal that happens to match one. A bare
 * date_range() meant that any package prefixed alongside this one had its
 * 'date_range' *array keys* rewritten too — wp-register-post-fields declares
 * a field type by that name, and the whole metabox stopped registering with
 * "Invalid field type". The get_ prefix removes the collision.
 */
if ( ! function_exists( 'get_date_range' ) ) {
	/**
	 * Get a predefined date range in UTC.
	 *
	 * @param string $range Range identifier (today, yesterday, last_week, last_30_days, etc).
	 *
	 * @return array{start: string, end: string} Start and end dates in UTC.
	 */
	function get_date_range( string $range ): array {
		return Dates::get_range( $range );
	}
}

if ( ! function_exists( 'is_date_fresh' ) ) {
	/**
	 * Check if a date is within a freshness threshold.
	 *
	 * @param string|null $utc_datetime UTC datetime to check.
	 * @param int         $hours        Hours threshold for freshness.
	 *
	 * @return bool True if date is fresh (within threshold).
	 */
	function is_date_fresh( ?string $utc_datetime, int $hours = 24 ): bool {
		return Dates::is_fresh( $utc_datetime, $hours );
	}
}

if ( ! function_exists( 'add_days' ) ) {
	/**
	 * Add days to a date.
	 *
	 * @param string $utc_datetime UTC datetime.
	 * @param int    $days         Number of days to add (can be negative).
	 *
	 * @return string Modified UTC datetime.
	 */
	function add_days( string $utc_datetime, int $days ): string {
		try {
			return Dates::add( $utc_datetime, $days );
		} catch ( Exception $e ) {
			return $utc_datetime;
		}
	}
}

if ( ! function_exists( 'get_date_range_options' ) ) {
	/**
	 * Get date range options for dropdowns.
	 *
	 * @param bool $as_options If true, returns array of value/label pairs. If false, returns associative array.
	 *
	 * @return array Array of options in requested format.
	 */
	function get_date_range_options( bool $as_options = false ): array {
		$ranges = Dates::get_range_options();

		if ( ! $as_options ) {
			return $ranges;
		}

		// Convert to value/label format
		$options = [];
		foreach ( $ranges as $key => $value ) {
			$options[] = [
				'value' => $key,
				'label' => $value,
			];
		}

		return $options;
	}
}
