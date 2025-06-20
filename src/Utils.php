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
class Utils {

	/**
	 * Process filter options into a select-friendly format
	 *
	 * @param array|callable $source Raw options or callback that returns options
	 *
	 * @return array Formatted options as key-value pairs
	 */
	public static function get_processed_options( $source ): array {
		$raw_options       = [];
		$processed_options = [];

		// Get raw options from either direct array or callback
		if ( is_callable( $source ) ) {
			$raw_options = call_user_func( $source );
		} elseif ( is_array( $source ) ) {
			$raw_options = $source;
		}

		// Convert to simple key => value format
		foreach ( $raw_options as $key => $value ) {
			// Handle already formatted key => value pairs
			if ( is_string( $key ) && ! is_array( $value ) ) {
				$processed_options[ $key ] = $value;
			} // Handle value/label object format
			elseif ( is_array( $value ) && isset( $value['value'] ) && isset( $value['label'] ) ) {
				$processed_options[ $value['value'] ] = $value['label'];
			}
		}

		return $processed_options;
	}

	/**
	 * Validate a date string
	 *
	 * @param string $date Date string to validate
	 *
	 * @return string Valid date string in Y-m-d format or empty string if invalid
	 */
	public static function validate_date_format( string $date ): string {
		if ( empty( $date ) ) {
			return '';
		}

		// Try to create a DateTime object from the date
		$dt = date_create( $date );

		if ( $dt === false ) {
			return '';
		}

		// Return the date in standard Y-m-d format
		return $dt->format( 'Y-m-d' );
	}

	/**
	 * Get display value for select options
	 *
	 * @param mixed $value   The selected value
	 * @param array $options Array of available options
	 *
	 * @return string Display value for the selected option
	 */
	public static function get_select_option_display( $value, array $options ): string {
		// Handle array of options with value/label pairs
		foreach ( $options as $option_key => $option_value ) {
			if ( is_array( $option_value ) && isset( $option_value['value'] ) && $option_value['value'] == $value ) {
				return $option_value['label'] ?? (string) $value;
			} elseif ( $option_key == $value ) {
				return $option_value;
			}
		}

		// Return original value if no match found
		return (string) $value;
	}

}