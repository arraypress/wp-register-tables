<?php
/**
 * Global Country Helper Functions
 *
 * Provides convenient global functions for common country operations.
 * These functions are wrappers around the ArrayPress\Countries\Countries class.
 *
 * @package ArrayPress\Countries
 * @since   1.0.0
 */

// Exit if accessed directly
// return, not exit. This file is a Composer `files` autoload entry, so it runs
// whenever anything requires the autoloader -- phpunit, phpcs, a composer
// script. Ending the process there kills the tool with status 0 and no output,
// which reads as success: a lint that never looked at a file, or a test suite
// that never ran, both report as passing.
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use ArrayPress\Countries\Countries;

if ( ! function_exists( 'get_country_name' ) ) {
	/**
	 * Get country name by code.
	 *
	 * @param string $code Country code (case-insensitive).
	 *
	 * @return string Country name or original code if not found.
	 */
	function get_country_name( string $code ): string {
		return Countries::get_name( $code );
	}
}

if ( ! function_exists( 'get_country_flag' ) ) {
	/**
	 * Get emoji flag for country.
	 *
	 * @param string $code Country code.
	 *
	 * @return string Emoji flag or empty string.
	 */
	function get_country_flag( string $code ): string {
		return Countries::get_flag( $code );
	}
}

if ( ! function_exists( 'get_country_continent' ) ) {
	/**
	 * Get continent for country.
	 *
	 * @param string $code Country code.
	 *
	 * @return string|null Continent name or null.
	 */
	function get_country_continent( string $code ): ?string {
		return Countries::get_continent( $code );
	}
}

if ( ! function_exists( 'sanitize_country_code' ) ) {
	/**
	 * Validate and sanitize country code.
	 *
	 * @param string $code Country code to validate.
	 *
	 * @return string|null Sanitized country code or null if invalid.
	 */
	function sanitize_country_code( string $code ): ?string {
		return Countries::sanitize( $code );
	}
}

if ( ! function_exists( 'get_country_options' ) ) {
	/**
	 * Get all countries as code => name pairs.
	 *
	 * @return array Array of country code => country name pairs.
	 */
	function get_country_options(): array {
		return Countries::all();
	}
}

if ( ! function_exists( 'render_country' ) ) {
	/**
	 * Render a country code as formatted HTML with flag and name.
	 *
	 * @param string $code         Country code (ISO 3166-1 alpha-2).
	 * @param bool   $include_flag Include emoji flag (default true).
	 * @param bool   $include_name Include country name (default true).
	 *
	 * @return string|null Formatted country HTML or null if empty/invalid.
	 */
	function render_country( string $code, bool $include_flag = true, bool $include_name = true ): ?string {
		return Countries::render( $code, $include_flag, $include_name );
	}
}