<?php
/**
 * Utilities Class
 *
 * Provides utility methods for formatting and data handling.
 *
 * @package     ArrayPress\CustomTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Utils Class
 *
 * Static utility methods for formatting and data handling.
 *
 * @since 1.0.0
 */
class Format {

	/**
	 * Format a value as currency with proper internationalization
	 *
	 * @param float       $value         The numeric value to format
	 * @param string      $currency      Currency symbol (default: '$')
	 * @param int         $decimals      Number of decimal places (default: 2)
	 * @param string      $position      Currency symbol position ('before', 'after', 'before_space', 'after_space')
	 * @param string|null $thousands_sep Thousands separator (null = use WordPress default)
	 * @param string|null $decimal_point Decimal point character (null = use WordPress default)
	 *
	 * @return string Formatted currency value with proper HTML escaping
	 */
	public static function money(
		float $value,
		string $currency = '$',
		int $decimals = 2,
		string $position = 'before',
		?string $thousands_sep = null,
		?string $decimal_point = null
	): string {
		// Format with WordPress's function or custom separators if provided
		if ( $thousands_sep !== null && $decimal_point !== null ) {
			$formatted = number_format( $value, $decimals, $decimal_point, $thousands_sep );
		} else {
			$formatted = number_format_i18n( $value, $decimals );
		}

		// Handle currency position with appropriate spacing
		switch ( $position ) {
			case 'after':
				$result = $formatted . $currency;
				break;
			case 'before_space':
				$result = $currency . ' ' . $formatted;
				break;
			case 'after_space':
				$result = $formatted . ' ' . $currency;
				break;
			default:
				$result = $currency . $formatted;
		}

		return esc_html( $result );
	}

	/**
	 * Format a numeric value using WordPress i18n
	 *
	 * @param float|int $value    The numeric value to format
	 * @param int       $decimals Number of decimal places
	 *
	 * @return string Formatted number with proper HTML escaping
	 */
	public static function number( $value, int $decimals = 0 ): string {
		return esc_html( number_format_i18n( (float) $value, $decimals ) );
	}

	/**
	 * Format a value as a percentage
	 *
	 * @param float|int $value        The numeric value to format
	 * @param int       $decimals     Number of decimal places
	 * @param bool      $include_sign Whether to include % sign
	 *
	 * @return string Formatted percentage with proper HTML escaping
	 */
	public static function percentage( $value, int $decimals = 1, bool $include_sign = true ): string {
		$formatted = number_format_i18n( (float) $value, $decimals );

		return esc_html( $include_sign ? $formatted . '%' : $formatted );
	}

}