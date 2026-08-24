<?php
/**
 * WordPress Simple Date Utils
 *
 * Lightweight date utilities for WordPress without external dependencies.
 * Focused on UTC storage and local display conversion.
 *
 * @package     ArrayPress\DateUtils
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\DateUtils;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Simple Date utilities for WordPress
 */
class Dates {

	/* ========================================================================
	 * CORE DATE OPERATIONS
	 * ======================================================================== */

	/**
	 * Get current UTC datetime
	 *
	 * @param string $format Format string (default MySQL format)
	 *
	 * @return string UTC datetime
	 */
	public static function now_utc( string $format = 'Y-m-d H:i:s' ): string {
		return gmdate( $format );
	}

	/**
	 * Get current local datetime
	 *
	 * @param string $format Format string (default MySQL format)
	 *
	 * @return string Local datetime
	 */
	public static function now_local( string $format = 'Y-m-d H:i:s' ): string {
		return current_time( $format );
	}

	/**
	 * Convert local datetime to UTC for database storage
	 *
	 * @param string $local_datetime Local datetime string
	 * @param string $format         Output format
	 *
	 * @return string UTC datetime
	 */
	public static function to_utc( string $local_datetime, string $format = 'Y-m-d H:i:s' ): string {
		return get_gmt_from_date( $local_datetime, $format );
	}

	/**
	 * Convert UTC datetime to local for display
	 *
	 * @param string $utc_datetime UTC datetime from database
	 * @param string $format       Output format (empty = WP settings)
	 *
	 * @return string Local datetime
	 */
	public static function to_local( string $utc_datetime, string $format = '' ): string {
		if ( self::is_zero( $utc_datetime ) ) {
			return '—';
		}

		if ( empty( $format ) ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return get_date_from_gmt( $utc_datetime, $format );
	}

	/**
	 * Convert Unix timestamp to MySQL datetime
	 *
	 * @param int $timestamp Unix timestamp
	 *
	 * @return string MySQL datetime in UTC
	 */
	public static function timestamp_to_mysql( int $timestamp ): string {
		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Convert MySQL datetime to Unix timestamp
	 *
	 * @param string $datetime MySQL datetime
	 *
	 * @return int Unix timestamp
	 */
	public static function to_timestamp( string $datetime ): int {
		return strtotime( $datetime . ' UTC' );
	}

	/* ========================================================================
	 * DATE ARITHMETIC
	 * ======================================================================== */

	/**
	 * Add time to a UTC date
	 *
	 * @param string $utc_datetime UTC datetime
	 * @param int    $amount       Amount to add
	 * @param string $unit         Unit: days, hours, minutes, seconds, weeks, months, years
	 *
	 * @return string Modified UTC datetime
	 * @throws Exception
	 */
	public static function add( string $utc_datetime, int $amount, string $unit = 'days' ): string {
		$timestamp = strtotime( $utc_datetime . ' UTC' );

		switch ( $unit ) {
			case 'seconds':
				$timestamp += $amount;
				break;
			case 'minutes':
				$timestamp += $amount * MINUTE_IN_SECONDS;
				break;
			case 'hours':
				$timestamp += $amount * HOUR_IN_SECONDS;
				break;
			case 'days':
				$timestamp += $amount * DAY_IN_SECONDS;
				break;
			case 'weeks':
				$timestamp += $amount * WEEK_IN_SECONDS;
				break;
			case 'months':
				return self::add_months( $utc_datetime, $amount );
			case 'years':
				return self::add_years( $utc_datetime, $amount );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Subtract time from a UTC date
	 *
	 * @param string $utc_datetime UTC datetime
	 * @param int    $amount       Amount to subtract
	 * @param string $unit         Unit: days, hours, minutes, seconds, weeks, months, years
	 *
	 * @return string Modified UTC datetime
	 * @throws Exception
	 */
	public static function subtract( string $utc_datetime, int $amount, string $unit = 'days' ): string {
		return self::add( $utc_datetime, - $amount, $unit );
	}

	/**
	 * Add months to a UTC date (precise calculation)
	 *
	 * @param string $utc_datetime UTC datetime
	 * @param int    $months       Number of months to add
	 *
	 * @return string Modified UTC datetime
	 * @throws Exception
	 */
	public static function add_months( string $utc_datetime, int $months ): string {
		$date = new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) );
		$date->add( new DateInterval( "P{$months}M" ) );

		return $date->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Add years to a UTC date (precise calculation)
	 *
	 * @param string $utc_datetime UTC datetime
	 * @param int    $years        Number of years to add
	 *
	 * @return string Modified UTC datetime
	 * @throws Exception
	 */
	public static function add_years( string $utc_datetime, int $years ): string {
		$date = new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) );
		$date->add( new DateInterval( "P{$years}Y" ) );

		return $date->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Get difference between two dates
	 *
	 * @param string $date1_utc First UTC datetime
	 * @param string $date2_utc Second UTC datetime
	 * @param string $unit      Unit to return: days, hours, minutes, seconds
	 *
	 * @return int Difference in specified unit
	 */
	public static function diff( string $date1_utc, string $date2_utc, string $unit = 'days' ): int {
		$timestamp1   = strtotime( $date1_utc . ' UTC' );
		$timestamp2   = strtotime( $date2_utc . ' UTC' );
		$diff_seconds = abs( $timestamp1 - $timestamp2 );

		switch ( $unit ) {
			case 'seconds':
				return $diff_seconds;
			case 'minutes':
				return intval( $diff_seconds / MINUTE_IN_SECONDS );
			case 'hours':
				return intval( $diff_seconds / HOUR_IN_SECONDS );
			case 'weeks':
				return intval( $diff_seconds / WEEK_IN_SECONDS );
			case 'months':
				return intval( $diff_seconds / MONTH_IN_SECONDS );
			case 'years':
				return intval( $diff_seconds / YEAR_IN_SECONDS );
			default:
				return intval( $diff_seconds / DAY_IN_SECONDS );
		}
	}

	/* ========================================================================
	 * FORMATTING & DISPLAY
	 * ======================================================================== */

	/**
	 * Format UTC date using WordPress settings with i18n
	 *
	 * @param string $utc_datetime UTC datetime
	 * @param string $type         Type: 'date', 'time', or 'datetime'
	 *
	 * @return string Formatted datetime
	 */
	public static function format( string $utc_datetime, string $type = 'datetime' ): string {
		if ( self::is_zero( $utc_datetime ) ) {
			return '—';
		}

		$timestamp = strtotime( $utc_datetime . ' UTC' );

		switch ( $type ) {
			case 'date':
				$format = get_option( 'date_format' );
				break;
			case 'time':
				$format = get_option( 'time_format' );
				break;
			default:
				$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return wp_date( $format, $timestamp );
	}

	/**
	 * Get human-readable time difference
	 *
	 * @param string $utc_datetime UTC datetime
	 *
	 * @return string Human-readable difference
	 */
	public static function human_diff( string $utc_datetime ): string {
		if ( self::is_zero( $utc_datetime ) ) {
			return '—';
		}

		$timestamp = strtotime( $utc_datetime . ' UTC' );

		return sprintf(
		/* translators: %s: human time difference */
			__( '%s ago', 'arraypress' ),
			human_time_diff( $timestamp )
		);
	}

	/**
	 * Format for admin display with relative time
	 *
	 * @param string $utc_datetime UTC datetime
	 *
	 * @return string HTML formatted date with relative time
	 */
	public static function format_admin( string $utc_datetime ): string {
		if ( self::is_zero( $utc_datetime ) ) {
			return '—';
		}

		$formatted = self::format( $utc_datetime );
		$relative  = self::human_diff( $utc_datetime );

		return sprintf(
			'%s<br><small class="description">%s</small>',
			esc_html( $formatted ),
			esc_html( $relative )
		);
	}

	/**
	 * Format datetime with empty value handling
	 *
	 * @param string|null $utc_datetime UTC datetime
	 * @param string      $empty_text   Text to show for empty/zero dates
	 * @param string      $format_type  Format type: 'datetime', 'date', 'time', 'human', 'admin'
	 *
	 * @return string Formatted datetime or empty text
	 */
	public static function format_or_empty( ?string $utc_datetime, string $empty_text = '—', string $format_type = 'datetime' ): string {
		if ( ! $utc_datetime || self::is_zero( $utc_datetime ) ) {
			return $empty_text;
		}

		switch ( $format_type ) {
			case 'human':
				return self::human_diff( $utc_datetime );
			case 'admin':
				return self::format_admin( $utc_datetime );
			default:
				return self::format( $utc_datetime, $format_type );
		}
	}

	/**
	 * Format a date range for display
	 *
	 * @param string $start_utc UTC start datetime
	 * @param string $end_utc   UTC end datetime
	 * @param string $format    Date format (defaults to 'M j, Y')
	 * @param string $separator Separator between dates (defaults to ' — ')
	 *
	 * @return string Formatted date range
	 */
	public static function format_range( string $start_utc, string $end_utc, string $format = 'M j, Y', string $separator = ' — ' ): string {
		$start_formatted = self::to_local( $start_utc, $format );
		$end_formatted   = self::to_local( $end_utc, $format );

		return $start_formatted . $separator . $end_formatted;
	}

	/* ========================================================================
	 * VALIDATION & CHECKS
	 * ======================================================================== */

	/**
	 * Check if date is zero/empty
	 *
	 * @param string|null $date Date value
	 *
	 * @return bool True if zero/empty
	 */
	public static function is_zero( ?string $date ): bool {
		return empty( $date )
				|| $date === '0000-00-00 00:00:00'
				|| $date === '0000-00-00';
	}

	/**
	 * Validate date string
	 *
	 * @param string $date Date string to validate
	 *
	 * @return bool True if valid date
	 */
	public static function is_valid( string $date ): bool {
		return strtotime( $date ) !== false;
	}

	/**
	 * Check if date matches specific format
	 *
	 * @param string $date   Date string to check
	 * @param string $format Expected format (e.g., 'Y-m-d')
	 *
	 * @return bool True if matches format
	 */
	public static function is_format( string $date, string $format ): bool {
		$d = DateTime::createFromFormat( $format, $date );

		return $d && $d->format( $format ) === $date;
	}

	/**
	 * Check if date has expired
	 *
	 * @param string $utc_datetime UTC expiration datetime
	 * @param int    $grace_hours  Optional grace period in hours
	 *
	 * @return bool True if expired
	 */
	public static function is_expired( string $utc_datetime, int $grace_hours = 0 ): bool {
		$expiry = strtotime( $utc_datetime . ' UTC' );

		if ( $grace_hours > 0 ) {
			$expiry += $grace_hours * HOUR_IN_SECONDS;
		}

		return time() > $expiry;
	}

	/**
	 * Check if date is in the past
	 *
	 * @param string $utc_datetime UTC datetime
	 *
	 * @return bool True if past
	 */
	public static function is_past( string $utc_datetime ): bool {
		return strtotime( $utc_datetime . ' UTC' ) < time();
	}

	/**
	 * Check if date is in the future
	 *
	 * @param string $utc_datetime UTC datetime
	 *
	 * @return bool True if future
	 */
	public static function is_future( string $utc_datetime ): bool {
		return strtotime( $utc_datetime . ' UTC' ) > time();
	}

	/**
	 * Check if date is within range
	 *
	 * @param string $date_utc  UTC datetime to check
	 * @param string $start_utc UTC start datetime
	 * @param string $end_utc   UTC end datetime
	 *
	 * @return bool True if within range
	 */
	public static function in_range( string $date_utc, string $start_utc, string $end_utc ): bool {
		$date  = strtotime( $date_utc . ' UTC' );
		$start = strtotime( $start_utc . ' UTC' );
		$end   = strtotime( $end_utc . ' UTC' );

		return $date >= $start && $date <= $end;
	}

	/**
	 * Check if a datetime is older than a specified threshold (is stale)
	 *
	 * @param string|null $utc_datetime UTC datetime to check
	 * @param int         $threshold    Threshold value
	 * @param string      $unit         Unit: hours, days, minutes, seconds, weeks
	 *
	 * @return bool True if datetime is older than threshold or empty
	 */
	public static function is_stale( ?string $utc_datetime, int $threshold, string $unit = 'hours' ): bool {
		if ( ! $utc_datetime || self::is_zero( $utc_datetime ) ) {
			return true;
		}

		$elapsed = self::diff( $utc_datetime, self::now_utc(), $unit );

		return $elapsed >= $threshold;
	}

	/**
	 * Check if a datetime is within a specified threshold (is fresh)
	 *
	 * @param string|null $utc_datetime UTC datetime to check
	 * @param int         $threshold    Threshold value
	 * @param string      $unit         Unit: hours, days, minutes, seconds, weeks
	 *
	 * @return bool True if datetime is within threshold and not empty
	 */
	public static function is_fresh( ?string $utc_datetime, int $threshold, string $unit = 'hours' ): bool {
		if ( ! $utc_datetime || self::is_zero( $utc_datetime ) ) {
			return false;
		}

		$elapsed = self::diff( $utc_datetime, self::now_utc(), $unit );

		return $elapsed < $threshold;
	}

	/* ========================================================================
	 * BUSINESS LOGIC
	 * ======================================================================== */

	/**
	 * Check if date is a weekend
	 *
	 * @param string $utc_datetime UTC datetime
	 *
	 * @return bool True if weekend (Saturday or Sunday)
	 */
	public static function is_weekend( string $utc_datetime ): bool {
		$day = gmdate( 'N', strtotime( $utc_datetime . ' UTC' ) );

		return in_array( $day, [ '6', '7' ], true ); // Saturday, Sunday
	}

	/**
	 * Calculate age in years from birthdate
	 *
	 * @param string $birth_date_utc Birthdate in UTC
	 *
	 * @return int Age in years
	 * @throws Exception
	 */
	public static function get_age( string $birth_date_utc ): int {
		$birth = new DateTime( $birth_date_utc, new DateTimeZone( 'UTC' ) );
		$now   = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

		return $birth->diff( $now )->y;
	}

	/* ========================================================================
	 * DATE RANGES
	 * ======================================================================== */

	/**
	 * Get date ranges (predefined periods)
	 *
	 * @param string $range          Range identifier
	 * @param bool   $local_timezone If true, calculate in local timezone first then convert to UTC
	 *
	 * @return array{start: string, end: string} Start/end times in UTC
	 */
	public static function get_range( string $range, bool $local_timezone = true ): array {
		if ( ! $local_timezone ) {
			return self::get_range_utc( $range );
		}

		// Calculate in local timezone first (more intuitive for users).
		//
		// current_time( 'timestamp' ) deliberately returns a site-timezone
		// shifted value rather than a real Unix timestamp. Formatting it with
		// gmdate() — WordPress keeps PHP's default timezone at UTC — is what
		// turns it back into local wall-clock date parts. The sniff is right
		// that this is not a UTC timestamp; that is the point, and every read
		// of it below is paired with gmdate() rather than a timezone-aware
		// call.
		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$local_timestamp = current_time( 'timestamp' );

		switch ( $range ) {
			case 'today':
				$start = current_time( 'Y-m-d 00:00:00' );
				$end   = current_time( 'Y-m-d 23:59:59' );
				break;

			case 'yesterday':
				$yesterday = gmdate( 'Y-m-d', $local_timestamp - DAY_IN_SECONDS );
				$start     = $yesterday . ' 00:00:00';
				$end       = $yesterday . ' 23:59:59';
				break;

			case 'this_week':
				$week_start = strtotime( 'monday this week', $local_timestamp );
				$week_end   = strtotime( 'sunday this week', $local_timestamp ) + 86399;
				$start      = gmdate( 'Y-m-d 00:00:00', $week_start );
				$end        = gmdate( 'Y-m-d 23:59:59', $week_end );
				break;

			case 'last_week':
				$week_start = strtotime( 'monday last week', $local_timestamp );
				$week_end   = strtotime( 'sunday last week', $local_timestamp ) + 86399;
				$start      = gmdate( 'Y-m-d 00:00:00', $week_start );
				$end        = gmdate( 'Y-m-d 23:59:59', $week_end );
				break;

			case 'this_month':
				$start = gmdate( 'Y-m-01 00:00:00', $local_timestamp );
				$end   = gmdate( 'Y-m-t 23:59:59', $local_timestamp );
				break;

			case 'last_month':
				$first_day = strtotime( 'first day of last month', $local_timestamp );
				$last_day  = strtotime( 'last day of last month', $local_timestamp );
				$start     = gmdate( 'Y-m-d 00:00:00', $first_day );
				$end       = gmdate( 'Y-m-d 23:59:59', $last_day );
				break;

			case 'this_quarter':
				$month               = gmdate( 'n', $local_timestamp );
				$year                = gmdate( 'Y', $local_timestamp );
				$quarter_start_month = ceil( $month / 3 ) * 3 - 2;
				$quarter_end_month   = $quarter_start_month + 2;
				$start               = gmdate( 'Y-m-d 00:00:00', mktime( 0, 0, 0, $quarter_start_month, 1, $year ) );
				$end                 = gmdate( 'Y-m-d 23:59:59', mktime( 23, 59, 59, $quarter_end_month + 1, 0, $year ) );
				break;

			case 'last_quarter':
				$month               = gmdate( 'n', $local_timestamp );
				$year                = gmdate( 'Y', $local_timestamp );
				$quarter_start_month = ceil( $month / 3 ) * 3 - 5;
				if ( $quarter_start_month < 1 ) {
					$quarter_start_month += 12;
					--$year;
				}
				$quarter_end_month = $quarter_start_month + 2;
				$start             = gmdate( 'Y-m-d 00:00:00', mktime( 0, 0, 0, $quarter_start_month, 1, $year ) );
				$end               = gmdate( 'Y-m-d 23:59:59', mktime( 23, 59, 59, $quarter_end_month + 1, 0, $year ) );
				break;

			case 'this_year':
				$start = gmdate( 'Y-01-01 00:00:00', $local_timestamp );
				$end   = gmdate( 'Y-12-31 23:59:59', $local_timestamp );
				break;

			case 'last_year':
				$year  = gmdate( 'Y', $local_timestamp ) - 1;
				$start = $year . '-01-01 00:00:00';
				$end   = $year . '-12-31 23:59:59';
				break;

			case 'last_7_days':
				$start = gmdate( 'Y-m-d 00:00:00', $local_timestamp - ( 6 * DAY_IN_SECONDS ) );
				$end   = gmdate( 'Y-m-d 23:59:59', $local_timestamp );
				break;

			case 'last_30_days':
				$start = gmdate( 'Y-m-d 00:00:00', $local_timestamp - ( 29 * DAY_IN_SECONDS ) );
				$end   = gmdate( 'Y-m-d 23:59:59', $local_timestamp );
				break;

			case 'last_90_days':
				$start = gmdate( 'Y-m-d 00:00:00', $local_timestamp - ( 89 * DAY_IN_SECONDS ) );
				$end   = gmdate( 'Y-m-d 23:59:59', $local_timestamp );
				break;

			case 'year_to_date':
				$start = gmdate( 'Y-01-01 00:00:00', $local_timestamp );
				$end   = gmdate( 'Y-m-d 23:59:59', $local_timestamp );
				break;

			case 'month_to_date':
				$start = gmdate( 'Y-m-01 00:00:00', $local_timestamp );
				$end   = gmdate( 'Y-m-d 23:59:59', $local_timestamp );
				break;

			case 'all_time':
				return [
					'start' => '1970-01-01 00:00:00',
					'end'   => self::now_utc(),
				];

			default:
				// Default to today
				$start = current_time( 'Y-m-d 00:00:00' );
				$end   = current_time( 'Y-m-d 23:59:59' );
				break;
		}

		// Convert local times to UTC
		return [
			'start' => self::to_utc( $start ),
			'end'   => self::to_utc( $end ),
		];
	}

	/**
	 * Get date range in pure UTC (for rolling windows)
	 *
	 * @param string $range Range identifier
	 *
	 * @return array{start: string, end: string} UTC range
	 */
	private static function get_range_utc( string $range ): array {
		$now = time();

		switch ( $range ) {
			case 'last_7_days':
				$start = gmdate( 'Y-m-d 00:00:00', $now - ( 6 * DAY_IN_SECONDS ) );
				$end   = gmdate( 'Y-m-d 23:59:59', $now );
				break;

			case 'last_30_days':
				$start = gmdate( 'Y-m-d 00:00:00', $now - ( 29 * DAY_IN_SECONDS ) );
				$end   = gmdate( 'Y-m-d 23:59:59', $now );
				break;

			case 'last_90_days':
				$start = gmdate( 'Y-m-d 00:00:00', $now - ( 89 * DAY_IN_SECONDS ) );
				$end   = gmdate( 'Y-m-d 23:59:59', $now );
				break;

			default:
				$start = gmdate( 'Y-m-d 00:00:00', $now );
				$end   = gmdate( 'Y-m-d 23:59:59', $now );
				break;
		}

		return [
			'start' => $start,
			'end'   => $end,
		];
	}

	/**
	 * Convert a local date range to UTC
	 *
	 * @param string $start_local Local start datetime
	 * @param string $end_local   Local end datetime
	 *
	 * @return array{start: string, end: string} UTC range
	 */
	public static function range_to_utc( string $start_local, string $end_local ): array {
		return [
			'start' => self::to_utc( $start_local ),
			'end'   => self::to_utc( $end_local ),
		];
	}

	/**
	 * Get date range with both UTC and local representations
	 *
	 * Returns UTC dates for database queries and local dates for display.
	 * Handles preset ranges, custom ranges, and the special 'all_time' case.
	 *
	 * @param string $range        Range identifier or 'custom'
	 * @param string $custom_start Custom start date (local, Y-m-d format) - only used if range is 'custom'
	 * @param string $custom_end   Custom end date (local, Y-m-d format) - only used if range is 'custom'
	 *
	 * @return array{start: string, end: string, start_local: string, end_local: string, preset: string}
	 */
	public static function get_range_full( string $range, string $custom_start = '', string $custom_end = '' ): array {
		// Handle custom range
		if ( $range === 'custom' && $custom_start && $custom_end ) {
			$utc_range = self::range_to_utc(
				$custom_start . ' 00:00:00',
				$custom_end . ' 23:59:59'
			);

			return [
				'start'       => $utc_range['start'],
				'end'         => $utc_range['end'],
				'start_local' => $custom_start,
				'end_local'   => $custom_end,
				'preset'      => 'custom',
			];
		}

		// Handle all_time specially
		if ( $range === 'all_time' ) {
			return [
				'start'       => '1970-01-01 00:00:00',
				'end'         => self::now_utc(),
				'start_local' => '1970-01-01',
				'end_local'   => self::now_local( 'Y-m-d' ),
				'preset'      => 'all_time',
			];
		}

		// Standard preset
		$utc_range = self::get_range( $range, true );

		return [
			'start'       => $utc_range['start'],
			'end'         => $utc_range['end'],
			'start_local' => self::to_local( $utc_range['start'], 'Y-m-d' ),
			'end_local'   => self::to_local( $utc_range['end'], 'Y-m-d' ),
			'preset'      => $range,
		];
	}

	/**
	 * Get the previous period for comparison
	 *
	 * Calculates a period of equal length immediately preceding the given range.
	 * Useful for period-over-period comparisons in reports.
	 *
	 * @param string $start_utc UTC start datetime of current period
	 * @param string $end_utc   UTC end datetime of current period
	 *
	 * @return array{start: string, end: string, start_local: string, end_local: string}
	 */
	public static function get_previous_period( string $start_utc, string $end_utc ): array {
		$days = self::diff( $start_utc, $end_utc, 'days' ) + 1;

		$prev_end   = self::subtract( $start_utc, 1, 'seconds' );
		$prev_start = self::subtract( $prev_end, $days, 'days' );

		return [
			'start'       => $prev_start,
			'end'         => $prev_end,
			'start_local' => self::to_local( $prev_start, 'Y-m-d' ),
			'end_local'   => self::to_local( $prev_end, 'Y-m-d' ),
		];
	}

	/**
	 * Get number of days in a date range (inclusive)
	 *
	 * @param string $start_utc UTC start datetime
	 * @param string $end_utc   UTC end datetime
	 *
	 * @return int Number of days
	 */
	public static function days_in_range( string $start_utc, string $end_utc ): int {
		return self::diff( $start_utc, $end_utc, 'days' ) + 1;
	}

	/**
	 * Get day boundaries (start and end of day)
	 *
	 * @param string $utc_datetime UTC datetime
	 *
	 * @return array{start: string, end: string} Start and end of day in UTC
	 */
	public static function day_bounds( string $utc_datetime ): array {
		$timestamp = strtotime( $utc_datetime . ' UTC' );

		return [
			'start' => gmdate( 'Y-m-d 00:00:00', $timestamp ),
			'end'   => gmdate( 'Y-m-d 23:59:59', $timestamp ),
		];
	}

	/* ========================================================================
	 * DATABASE HELPERS
	 * ======================================================================== */

	/**
	 * Build database query for date range
	 *
	 * @param string $column      Database column name
	 * @param string $start_local Local start datetime
	 * @param string $end_local   Local end datetime
	 *
	 * @return array{sql: string, values: array} Query parts
	 */
	public static function build_date_query( string $column, string $start_local, string $end_local ): array {
		return [
			'sql'    => "{$column} BETWEEN %s AND %s",
			'values' => [ self::to_utc( $start_local ), self::to_utc( $end_local ) ],
		];
	}

	/* ========================================================================
	 * OPTIONS & CONSTANTS
	 * ======================================================================== */

	/**
	 * Get available range options for dropdowns
	 *
	 * @param bool $include_custom  Whether to include the 'custom' option
	 * @param bool $include_alltime Whether to include the 'all_time' option
	 *
	 * @return array Array of value => label pairs
	 */
	public static function get_range_options( bool $include_custom = false, bool $include_alltime = false ): array {
		$options = [
			'today'         => __( 'Today', 'arraypress' ),
			'yesterday'     => __( 'Yesterday', 'arraypress' ),
			'this_week'     => __( 'This Week', 'arraypress' ),
			'last_week'     => __( 'Last Week', 'arraypress' ),
			'this_month'    => __( 'This Month', 'arraypress' ),
			'last_month'    => __( 'Last Month', 'arraypress' ),
			'this_quarter'  => __( 'This Quarter', 'arraypress' ),
			'last_quarter'  => __( 'Last Quarter', 'arraypress' ),
			'this_year'     => __( 'This Year', 'arraypress' ),
			'last_year'     => __( 'Last Year', 'arraypress' ),
			'last_7_days'   => __( 'Last 7 Days', 'arraypress' ),
			'last_30_days'  => __( 'Last 30 Days', 'arraypress' ),
			'last_90_days'  => __( 'Last 90 Days', 'arraypress' ),
			'year_to_date'  => __( 'Year to Date', 'arraypress' ),
			'month_to_date' => __( 'Month to Date', 'arraypress' ),
		];

		if ( $include_alltime ) {
			$options['all_time'] = __( 'All Time', 'arraypress' );
		}

		if ( $include_custom ) {
			$options['custom'] = __( 'Custom Range', 'arraypress' );
		}

		return $options;
	}

	/**
	 * Format seconds into human-readable duration
	 *
	 * @since 1.0.0
	 *
	 * @param int    $seconds Total seconds.
	 * @param string $format  Format style: 'short' (2h 15m), 'long' (2 hours, 15 minutes), 'compact' (2:15:00).
	 *
	 * @return string Formatted duration.
	 */
	public static function format_duration( int $seconds, string $format = 'short' ): string {
		if ( $seconds < 0 ) {
			return '—';
		}

		if ( $seconds === 0 ) {
			return match ( $format ) {
				'compact' => '0:00',
				'long'    => __( '0 seconds', 'arraypress' ),
				default   => '0s',
			};
		}

		$days    = (int) floor( $seconds / DAY_IN_SECONDS );
		$hours   = (int) floor( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
		$secs    = $seconds % MINUTE_IN_SECONDS;

		if ( $format === 'compact' ) {
			if ( $days > 0 ) {
				return sprintf( '%d:%02d:%02d:%02d', $days, $hours, $minutes, $secs );
			} elseif ( $hours > 0 ) {
				return sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
			}

			return sprintf( '%d:%02d', $minutes, $secs );
		}

		$parts = [];

		if ( $format === 'long' ) {
			if ( $days > 0 ) {
				/* translators: %d: number of days */
				$parts[] = sprintf( _n( '%d day', '%d days', $days, 'arraypress' ), $days );
			}
			if ( $hours > 0 ) {
				/* translators: %d: number of hours */
				$parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'arraypress' ), $hours );
			}
			if ( $minutes > 0 ) {
				/* translators: %d: number of minutes */
				$parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'arraypress' ), $minutes );
			}
			// Only show seconds for short durations (under 1 hour)
			if ( $secs > 0 && $seconds < HOUR_IN_SECONDS ) {
				/* translators: %d: number of seconds */
				$parts[] = sprintf( _n( '%d second', '%d seconds', $secs, 'arraypress' ), $secs );
			}
		} else {
			// Short format: 2d 5h 15m 30s
			if ( $days > 0 ) {
				$parts[] = $days . 'd';
			}
			if ( $hours > 0 ) {
				$parts[] = $hours . 'h';
			}
			if ( $minutes > 0 ) {
				$parts[] = $minutes . 'm';
			}
			// Only show seconds for short durations (under 1 hour)
			if ( $secs > 0 && $seconds < HOUR_IN_SECONDS ) {
				$parts[] = $secs . 's';
			}
		}

		return implode( ' ', $parts ) ?: '0s';
	}

	/**
	 * Render a UTC datetime as HTML with human diff and formatted title
	 *
	 * Returns a span element showing relative time (e.g., "2 hours ago")
	 * with the full formatted datetime as a title attribute on hover.
	 *
	 * @param string $utc_datetime UTC datetime from database.
	 *
	 * @return string|null HTML string or null if empty/zero date.
	 */
	public static function render_date( string $utc_datetime ): ?string {
		if ( self::is_zero( $utc_datetime ) ) {
			return null;
		}

		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( self::format( $utc_datetime ) ),
			esc_html( self::human_diff( $utc_datetime ) )
		);
	}

	/**
	 * Render a duration in seconds as formatted HTML
	 *
	 * Returns a span element with the human-readable duration.
	 * Supports short (2h 15m), long (2 hours, 15 minutes), and compact (2:15:00) formats.
	 *
	 * @param mixed  $value  Duration in seconds.
	 * @param string $format Format style: 'short', 'long', or 'compact'.
	 *
	 * @return string|null HTML string or null if invalid/negative value.
	 */
	public static function render_duration( $value, string $format = 'short' ): ?string {
		if ( ! is_numeric( $value ) || $value < 0 ) {
			return null;
		}

		return sprintf(
			'<span class="duration">%s</span>',
			esc_html( self::format_duration( (int) $value, $format ) )
		);
	}
}
