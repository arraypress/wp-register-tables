<?php
/**
 * Global Currency Helper Functions
 *
 * Provides convenient global functions for common currency operations.
 * These functions are wrappers around the ArrayPress\Currencies\Currency class.
 *
 * @package ArrayPress\Currencies
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use ArrayPress\Currencies\Currency;

/** =========================================================================
 *  Currency Data
 *  ======================================================================== */

if ( ! function_exists( 'get_currency_options' ) ) {
	/**
	 * Get currencies formatted as select options.
	 *
	 * @return array<string, string> Options keyed by currency code (e.g., 'USD' => 'USD — US Dollar').
	 * @since 1.0.0
	 */
	function get_currency_options(): array {
		return Currency::get_options();
	}
}

if ( ! function_exists( 'get_currency_country_options' ) ) {
	/**
	 * Get currencies formatted as select options with country names.
	 *
	 * @return array<string, string> Options keyed by currency code.
	 * @since 1.1.0
	 */
	function get_currency_country_options(): array {
		return Currency::get_country_options();
	}
}

if ( ! function_exists( 'get_currency_name' ) ) {
	/**
	 * Get the display name for a currency.
	 *
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return string Currency name (e.g., 'US Dollar') or code if not found.
	 * @since 1.0.0
	 */
	function get_currency_name( string $currency ): string {
		return Currency::get_name( $currency );
	}
}

if ( ! function_exists( 'get_currency_symbol' ) ) {
	/**
	 * Get the symbol for a currency.
	 *
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return string Currency symbol (e.g., '$', '£') or code if not found.
	 * @since 1.0.0
	 */
	function get_currency_symbol( string $currency ): string {
		return Currency::get_symbol( $currency );
	}
}

if ( ! function_exists( 'get_currency_decimals' ) ) {
	/**
	 * Get the number of decimal places for a currency.
	 *
	 * @param string $currency Currency code (e.g., 'USD', 'JPY').
	 *
	 * @return int Number of decimal places (e.g., 2 for USD, 0 for JPY).
	 * @since 1.0.0
	 */
	function get_currency_decimals( string $currency ): int {
		return Currency::get_decimals( $currency );
	}
}

if ( ! function_exists( 'get_currency_country' ) ) {
	/**
	 * Get the ISO 3166-1 alpha-2 country code for a currency.
	 *
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return string Country code (e.g., 'US', 'GB') or empty string if not found.
	 * @since 1.1.0
	 */
	function get_currency_country( string $currency ): string {
		return Currency::get_country( $currency );
	}
}

if ( ! function_exists( 'get_currency_country_name' ) ) {
	/**
	 * Get the country name for a currency.
	 *
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return string Country name (e.g., 'United States', 'United Kingdom') or empty string if not found.
	 * @since 1.1.0
	 */
	function get_currency_country_name( string $currency ): string {
		return Currency::get_country_name( $currency );
	}
}

if ( ! function_exists( 'get_currency_by_country' ) ) {
	/**
	 * Get currency code(s) for a given country code.
	 *
	 * @param string $country ISO 3166-1 alpha-2 country code (e.g., 'US', 'GB').
	 *
	 * @return array Currency codes associated with the country.
	 * @since 1.1.0
	 */
	function get_currency_by_country( string $country ): array {
		return Currency::get_by_country( $country );
	}
}

/** =========================================================================
 *  Formatting
 *  ======================================================================== */

if ( ! function_exists( 'format_currency' ) ) {
	/**
	 * Format a currency amount with symbol.
	 *
	 * Pass a locale string to use locale-aware formatting suitable for storefront
	 * display. Omit locale for simple symbol-prefix formatting suited to admin contexts.
	 * Locale-aware formatting requires the PHP intl extension and falls back to
	 * simple formatting if unavailable.
	 *
	 * @param int    $amount   Amount in the smallest unit (cents, pence, etc).
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 * @param string $locale   Optional locale override (e.g., 'de_DE').
	 *
	 * @return string Formatted amount with symbol (e.g., "$19.99").
	 * @since 1.0.0
	 */
	function format_currency( int $amount, string $currency, string $locale = '' ): string {
		if ( ! empty( $locale ) ) {
			return Currency::format_localized( $amount, $currency, $locale );
		}

		return Currency::format( $amount, $currency );
	}
}

if ( ! function_exists( 'format_currency_plain' ) ) {
	/**
	 * Format a currency amount without symbol.
	 *
	 * @param int    $amount   Amount in the smallest unit (cents, pence, etc).
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return string Formatted amount without symbol (e.g., "19.99").
	 * @since 1.0.0
	 */
	function format_currency_plain( int $amount, string $currency ): string {
		return Currency::format_plain( $amount, $currency );
	}
}

if ( ! function_exists( 'esc_currency_e' ) ) {
	/**
	 * Echo an escaped, formatted currency amount.
	 *
	 * @param int    $amount   Amount in the smallest unit (cents, pence, etc).
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return void
	 * @since 1.0.0
	 */
	function esc_currency_e( int $amount, string $currency ): void {
		echo esc_html( Currency::format( $amount, $currency ) );
	}
}

if ( ! function_exists( 'render_currency' ) ) {
	/**
	 * Render a formatted currency amount as HTML.
	 *
	 * @param int    $amount   Amount in the smallest unit (cents, pence, etc).
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return string HTML span with formatted amount (e.g., '<span class="price">$19.99</span>').
	 * @since 1.0.0
	 */
	function render_currency( int $amount, string $currency ): string {
		return Currency::render( $amount, $currency );
	}
}

/** =========================================================================
 *  Unit Conversion
 *  ======================================================================== */

if ( ! function_exists( 'to_currency_cents' ) ) {
	/**
	 * Convert a decimal amount to the smallest unit for Stripe.
	 *
	 * @param float  $amount   Decimal amount (e.g., 19.99).
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return int Amount in the smallest unit (e.g., 1999).
	 * @since 1.0.0
	 */
	function to_currency_cents( float $amount, string $currency ): int {
		return Currency::to_smallest_unit( $amount, $currency );
	}
}

if ( ! function_exists( 'from_currency_cents' ) ) {
	/**
	 * Convert from the smallest unit to a decimal amount.
	 *
	 * @param int    $amount   Amount in the smallest unit (e.g., 1999).
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return float Decimal amount (e.g., 19.99).
	 * @since 1.0.0
	 */
	function from_currency_cents( int $amount, string $currency ): float {
		return Currency::from_smallest_unit( $amount, $currency );
	}
}

/** =========================================================================
 *  Validation
 *  ======================================================================== */

if ( ! function_exists( 'is_currency_supported' ) ) {
	/**
	 * Check if a currency code is supported.
	 *
	 * @param string $currency Currency code (e.g., 'USD', 'GBP').
	 *
	 * @return bool True if supported by Stripe.
	 * @since 1.0.0
	 */
	function is_currency_supported( string $currency ): bool {
		return Currency::is_supported( $currency );
	}
}

if ( ! function_exists( 'is_currency_zero_decimal' ) ) {
	/**
	 * Check if a currency is zero-decimal.
	 *
	 * @param string $currency Currency code (e.g., 'JPY', 'KRW').
	 *
	 * @return bool True if zero-decimal currency.
	 * @since 1.0.0
	 */
	function is_currency_zero_decimal( string $currency ): bool {
		return Currency::is_zero_decimal( $currency );
	}
}