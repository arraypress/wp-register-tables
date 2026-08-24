<?php
/**
 * WordPress Subscription Date Utilities
 *
 * Handles subscription periods, billing dates, and Stripe-compatible intervals.
 *
 * @package     ArrayPress\DateUtils
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\DateUtils;

// Exit if accessed directly
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Subscription date utilities for WordPress
 *
 * @since 1.0.0
 */
class Subscriptions {

	/**
	 * Get subscription next billing date
	 *
	 * @param string $last_payment Last payment UTC datetime
	 * @param string $period       Period: daily, weekly, monthly, yearly, every_3_months, every_6_months
	 *
	 * @return string Next billing UTC datetime
	 * @throws Exception
	 * @since 1.0.0
	 */
	public static function next_billing( string $last_payment, string $period ): string {
		$timestamp = strtotime( $last_payment . ' UTC' );

		switch ( $period ) {
			case 'daily':
				$timestamp += DAY_IN_SECONDS;
				break;
			case 'weekly':
				$timestamp += WEEK_IN_SECONDS;
				break;
			case 'every_3_months':
				return Dates::add_months( $last_payment, 3 );
			case 'every_6_months':
				return Dates::add_months( $last_payment, 6 );
			case 'yearly':
				return Dates::add_years( $last_payment, 1 );
			default:
				return Dates::add_months( $last_payment, 1 );
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Calculate expiration date from now
	 *
	 * @param int    $duration Duration value
	 * @param string $unit     Unit: days, hours, minutes, months, years
	 *
	 * @return string UTC expiration datetime
	 * @throws Exception
	 * @since 1.0.0
	 *
	 */
	public static function calculate_expiration( int $duration, string $unit = 'days' ): string {
		return Dates::add( Dates::now_utc(), $duration, $unit );
	}

	/**
	 * Get subscription period options (Stripe-compatible)
	 *
	 * @param bool $include_custom Include custom option
	 *
	 * @return array Array of value => label pairs
	 * @since 1.0.0
	 */
	public static function get_period_options( bool $include_custom = false ): array {
		$options = [
			'daily'          => __( 'Daily', 'arraypress' ),
			'weekly'         => __( 'Weekly', 'arraypress' ),
			'monthly'        => __( 'Monthly', 'arraypress' ),
			'every_3_months' => __( 'Every 3 months', 'arraypress' ),
			'every_6_months' => __( 'Every 6 months', 'arraypress' ),
			'yearly'         => __( 'Yearly', 'arraypress' ),
		];

		if ( $include_custom ) {
			$options['custom'] = __( 'Custom', 'arraypress' );
		}

		return $options;
	}

	/**
	 * Get Stripe-compatible interval format
	 *
	 * @param string $period Our period name
	 *
	 * @return array{interval: string, interval_count: int} Stripe interval format
	 * @since 1.0.0
	 */
	public static function get_stripe_interval( string $period ): array {
		switch ( $period ) {
			case 'daily':
				return [
					'interval' => 'day',
					'interval_count' => 1,
				];
			case 'weekly':
				return [
					'interval' => 'week',
					'interval_count' => 1,
				];
			case 'every_3_months':
				return [
					'interval' => 'month',
					'interval_count' => 3,
				];
			case 'every_6_months':
				return [
					'interval' => 'month',
					'interval_count' => 6,
				];
			case 'yearly':
				return [
					'interval' => 'year',
					'interval_count' => 1,
				];
			default:
				return [
					'interval' => 'month',
					'interval_count' => 1,
				];
		}
	}

	/**
	 * Check if a trial period has expired
	 *
	 * @param string $trial_end_utc Trial end date in UTC
	 * @param int    $grace_hours   Optional grace period in hours
	 *
	 * @return bool True if trial has expired
	 * @since 1.0.0
	 */
	public static function is_trial_expired( string $trial_end_utc, int $grace_hours = 0 ): bool {
		return Dates::is_expired( $trial_end_utc, $grace_hours );
	}

	/**
	 * Calculate days until next renewal
	 *
	 * @param string $next_billing_utc Next billing date in UTC
	 *
	 * @return int Days until renewal (negative if past due)
	 * @since 1.0.0
	 */
	public static function days_until_renewal( string $next_billing_utc ): int {
		$now  = strtotime( Dates::now_utc() . ' UTC' );
		$next = strtotime( $next_billing_utc . ' UTC' );

		return (int) ceil( ( $next - $now ) / DAY_IN_SECONDS );
	}
}
