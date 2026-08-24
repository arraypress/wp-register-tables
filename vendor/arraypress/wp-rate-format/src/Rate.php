<?php
/**
 * WordPress Rate Formatting Library
 *
 * Handles formatting, rendering, and sanitization of rates, percentages,
 * and values that can be either percentage or currency amounts.
 *
 * @package     ArrayPress\RateFormat
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

namespace ArrayPress\RateFormat;

use ArrayPress\Currencies\Currency;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Rate formatting, rendering, and sanitization
 *
 * @since 1.0.0
 */
class Rate {

	/**
	 * Percentage type identifiers
	 *
	 * @var string[]
	 */
	private const PERCENTAGE_TYPES = [ 'percent', 'percentage', '%' ];

	/**
	 * Flat/fixed amount type identifiers
	 *
	 * @var string[]
	 */
	private const FLAT_TYPES = [ 'flat', 'fixed', 'amount' ];

	/* ========================================================================
	 * TYPE RESOLUTION
	 * ======================================================================== */

	/**
	 * Resolve rate type from an item object
	 *
	 * Checks for a {column}_type method or property first, then falls back
	 * to a generic type method or property on the item.
	 *
	 * @param object|null $item        Data object.
	 * @param string      $column_name Column name (used to find {column}_type).
	 *
	 * @return string|null Rate type or null if not found.
	 */
	public static function resolve_type( $item, string $column_name = 'rate' ): ?string {
		$type_property = $column_name . '_type';

		if ( $item && method_exists( $item, 'get_' . $type_property ) ) {
			return call_user_func( [ $item, 'get_' . $type_property ] );
		}

		if ( is_object( $item ) && property_exists( $item, $type_property ) ) {
			return $item->$type_property;
		}

		if ( $item && method_exists( $item, 'get_type' ) ) {
			return $item->get_type();
		}

		if ( is_object( $item ) && property_exists( $item, 'type' ) ) {
			return $item->type;
		}

		return null;
	}

	/**
	 * Check if a rate type is a percentage type
	 *
	 * @param string|null $type Rate type string.
	 *
	 * @return bool True if the type represents a percentage.
	 */
	public static function is_percentage_type( ?string $type ): bool {
		return $type !== null && in_array( $type, self::PERCENTAGE_TYPES, true );
	}

	/**
	 * Check if a rate type is a flat/fixed amount type
	 *
	 * @param string|null $type Rate type string.
	 *
	 * @return bool True if the type represents a flat amount.
	 */
	public static function is_flat_type( ?string $type ): bool {
		return $type !== null && in_array( $type, self::FLAT_TYPES, true );
	}

	/**
	 * Determine the effective format for a rate value
	 *
	 * Resolves the rate type from the item object and returns whether the
	 * value should be treated as a percentage or currency. When no type is
	 * found, guesses based on value range (0–100 = percentage).
	 *
	 * @param mixed       $value       Rate value.
	 * @param object|null $item        Data object.
	 * @param string      $column_name Column name for type resolution.
	 *
	 * @return string 'percentage' or 'currency'.
	 */
	public static function determine_format( $value, $item = null, string $column_name = 'rate' ): string {
		$type = self::resolve_type( $item, $column_name );

		if ( self::is_percentage_type( $type ) ) {
			return 'percentage';
		}

		if ( self::is_flat_type( $type ) ) {
			return 'currency';
		}

		// Guess based on value range
		if ( is_numeric( $value ) && $value >= 0 && $value <= 100 ) {
			return 'percentage';
		}

		return 'currency';
	}

	/* ========================================================================
	 * PERCENTAGE FORMATTING
	 * ======================================================================== */

	/**
	 * Format a percentage value as a plain string
	 *
	 * @param mixed $value    Percentage value (e.g., 15 for 15%).
	 * @param int   $decimals Number of decimal places (default 0).
	 *
	 * @return string|null Formatted percentage string or null if not numeric.
	 */
	public static function format_percentage( $value, int $decimals = 0 ): ?string {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		return number_format_i18n( (float) $value, $decimals ) . '%';
	}

	/**
	 * Render a percentage value as HTML
	 *
	 * @param mixed $value    Percentage value.
	 * @param int   $decimals Number of decimal places (default 0).
	 *
	 * @return string|null HTML string or null if not numeric.
	 */
	public static function render_percentage( $value, int $decimals = 0 ): ?string {
		$formatted = self::format_percentage( $value, $decimals );

		if ( $formatted === null ) {
			return null;
		}

		return sprintf( '<span class="percentage">%s</span>', esc_html( $formatted ) );
	}

	/* ========================================================================
	 * RATE FORMATTING
	 * ======================================================================== */

	/**
	 * Format a rate value as a plain string (percentage or currency)
	 *
	 * Resolves the rate type from the item object and formats accordingly.
	 *
	 * @param mixed       $value       Rate value.
	 * @param object|null $item        Data object.
	 * @param string      $column_name Column name for type resolution.
	 *
	 * @return string|null Formatted string or null if not numeric.
	 */
	public static function format( $value, $item = null, string $column_name = 'rate' ): ?string {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$format = self::determine_format( $value, $item, $column_name );

		if ( $format === 'percentage' ) {
			return self::format_percentage( $value );
		}

		$currency = Currency::resolve( $item );

		return Currency::format( intval( $value ), $currency );
	}

	/**
	 * Render a rate value as HTML (percentage or currency)
	 *
	 * Resolves the rate type from the item object and renders accordingly.
	 * Falls back to guessing based on value range when no type is found.
	 *
	 * @param mixed       $value       Rate value.
	 * @param object|null $item        Data object.
	 * @param string      $column_name Column name for type resolution.
	 *
	 * @return string|null HTML string or null if not numeric.
	 */
	public static function render( $value, $item = null, string $column_name = 'rate' ): ?string {
		if ( ! is_numeric( $value ) ) {
			return null;
		}

		$format = self::determine_format( $value, $item, $column_name );

		if ( $format === 'percentage' ) {
			return self::render_percentage( $value );
		}

		return Currency::render( $value, $item );
	}

	/* ========================================================================
	 * SANITIZATION
	 * ======================================================================== */

	/**
	 * Sanitize a percentage value
	 *
	 * Ensures the value is numeric and optionally clamps it within bounds.
	 *
	 * @param mixed     $value Percentage value to sanitize.
	 * @param float     $min   Minimum allowed value (default 0).
	 * @param float     $max   Maximum allowed value (default 100).
	 * @param int       $decimals Number of decimal places to round to (default 2).
	 *
	 * @return float Sanitized percentage value.
	 */
	public static function sanitize_percentage( $value, float $min = 0, float $max = 100, int $decimals = 2 ): float {
		$value = is_numeric( $value ) ? (float) $value : 0.0;

		$value = max( $min, min( $max, $value ) );

		return round( $value, $decimals );
	}

	/**
	 * Sanitize a rate value based on its type
	 *
	 * For percentage types, clamps between 0–100. For flat types, ensures
	 * a non-negative integer (smallest currency unit). When no type is
	 * provided, sanitizes based on the determined format.
	 *
	 * @param mixed       $value       Rate value to sanitize.
	 * @param object|null $item        Data object for type resolution.
	 * @param string      $column_name Column name for type resolution.
	 *
	 * @return int|float Sanitized value (float for percentage, int for currency).
	 */
	public static function sanitize_rate( $value, $item = null, string $column_name = 'rate' ) {
		$format = self::determine_format( $value, $item, $column_name );

		if ( $format === 'percentage' ) {
			return self::sanitize_percentage( $value );
		}

		// Currency: ensure non-negative integer (smallest unit)
		return max( 0, intval( $value ) );
	}

	/* ========================================================================
	 * VALIDATION
	 * ======================================================================== */

	/**
	 * Validate a percentage value
	 *
	 * @param mixed $value Percentage value to validate.
	 * @param float $min   Minimum allowed value (default 0).
	 * @param float $max   Maximum allowed value (default 100).
	 *
	 * @return bool True if valid percentage within bounds.
	 */
	public static function is_valid_percentage( $value, float $min = 0, float $max = 100 ): bool {
		if ( ! is_numeric( $value ) ) {
			return false;
		}

		$float = (float) $value;

		return $float >= $min && $float <= $max;
	}

	/**
	 * Validate a rate value based on its type
	 *
	 * @param mixed       $value       Rate value to validate.
	 * @param object|null $item        Data object for type resolution.
	 * @param string      $column_name Column name for type resolution.
	 *
	 * @return bool True if valid rate value.
	 */
	public static function is_valid_rate( $value, $item = null, string $column_name = 'rate' ): bool {
		if ( ! is_numeric( $value ) ) {
			return false;
		}

		$format = self::determine_format( $value, $item, $column_name );

		if ( $format === 'percentage' ) {
			return self::is_valid_percentage( $value );
		}

		// Currency: must be non-negative integer
		return intval( $value ) >= 0;
	}

}