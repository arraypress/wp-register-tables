<?php
/**
 * Table Instance UI Trait
 *
 * Provides UI functionality for the Table_Instance class.
 *
 * @package     SugarCart\Admin
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Traits;

// Exit if accessed directly
use Elementify\Create;

defined( 'ABSPATH' ) || exit;

/**
 * Trait_Table_Instance_UI
 *
 * UI functionality for the Table_Instance class
 */
trait Utils {

	/**
	 * Process filter options into a select-friendly format
	 *
	 * @param array|callable $source Raw options or callback that returns options
	 *
	 * @return array Formatted options as key-value pairs
	 */
	protected function get_processed_options( $source ): array {
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
	 * Build HTML attributes string from an array of attributes
	 *
	 * @param array $attributes Array of attribute name => value pairs
	 *
	 * @return string HTML attributes string
	 */
	protected function build_attributes( array $attributes ): string {
		$html_attributes = [];

		foreach ( $attributes as $name => $value ) {
			// Skip attributes with empty values
			if ( $value === '' || $value === null ) {
				continue;
			}

			// Boolean attributes (just the name)
			if ( is_bool( $value ) && $value ) {
				$html_attributes[] = esc_attr( $name );
				continue;
			}

			// Regular attributes with values
			if ( ! is_bool( $value ) ) {
				$html_attributes[] = sprintf( '%s="%s"', esc_attr( $name ), esc_attr( $value ) );
			}
		}

		return implode( ' ', $html_attributes );
	}

	/**
	 * Validate a date string
	 *
	 * @param string $date Date string to validate
	 *
	 * @return string Valid date string or empty string
	 */
	protected function validate_date_format( string $date ): string {
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

}