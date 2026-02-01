<?php
/**
 * Column Formatting Utilities
 *
 * Handles automatic formatting of column values based on naming patterns.
 *
 * @package     ArrayPress\WP\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Utils;

use ArrayPress\Countries\Countries;
use ArrayPress\Currencies\Currency;
use ArrayPress\DateUtils\Dates;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class Columns
 *
 * Provides automatic column value formatting based on column names and value types.
 *
 * @since 1.0.0
 */
class Columns {

	/**
	 * Column names that should be formatted as dates
	 *
	 * @var array
	 */
	private static array $date_columns = [
		'created',
		'updated',
		'modified',
		'registered',
		'last_sync',
		'last_login',
		'expires',
		'expiration',
		'published',
		'deleted',
	];

	/**
	 * Column name patterns that indicate date columns
	 *
	 * @var array
	 */
	private static array $date_patterns = [
		'_at',
		'date',
		'_date',
	];

	/**
	 * Column name patterns that indicate price/money columns
	 *
	 * @var array
	 */
	private static array $price_patterns = [
		'price',
		'total',
		'amount',
		'_spent',
		'subtotal',
		'discount',
		'tax',
		'fee',
		'cost',
		'revenue',
		'balance',
	];

	/**
	 * Column name patterns that indicate count columns
	 *
	 * @var array
	 */
	private static array $count_patterns = [
		'_count',
		'count',
		'limit',
		'quantity',
		'qty',
	];

	/**
	 * Column names that should be formatted as booleans
	 *
	 * @var array
	 */
	private static array $boolean_columns = [
		'test',
		'active',
		'enabled',
		'verified',
		'featured',
		'published',
	];

	/**
	 * Auto-format a column value based on column name patterns
	 *
	 * @param string $column_name   Column name
	 * @param mixed  $value         Column value
	 * @param object $item          Data object
	 * @param array  $status_styles Custom status styles
	 * @param array  $views         Views configuration (for status labels)
	 *
	 * @return string Formatted HTML
	 */
	public static function auto_format(
		string $column_name,
		$value,
		$item,
		array $status_styles = [],
		array $views = []
	): string {
		// Handle empty values
		if ( self::is_empty( $value ) ) {
			return self::render_empty();
		}

		// Email columns
		if ( self::is_email_column( $column_name ) ) {
			return self::format_email( $value );
		}

		// Country columns
		if ( self::is_country_column( $column_name ) ) {
			return self::format_country( $value );
		}

		// Date columns
		if ( self::is_date_column( $column_name ) ) {
			return self::format_date( $value );
		}

		// Price/money columns
		if ( self::is_price_column( $column_name ) ) {
			return self::format_price( $value, $item );
		}

		// Status columns
		if ( self::is_status_column( $column_name ) ) {
			return StatusBadge::render( $value, $status_styles, $views );
		}

		// Count columns
		if ( self::is_count_column( $column_name ) ) {
			return self::format_count( $value );
		}

		// URL columns
		if ( self::is_url_column( $column_name ) ) {
			return self::format_url( $value, $column_name );
		}

		// Boolean columns
		if ( self::is_boolean_column( $column_name ) ) {
			return self::format_boolean( $value, $column_name );
		}

		// Default: escape and return
		return esc_html( (string) $value );
	}

	/* ========================================================================
	 * FORMATTERS
	 * ======================================================================== */

	/**
	 * Format empty value
	 *
	 * @return string Empty placeholder HTML
	 */
	public static function render_empty(): string {
		return '<span aria-hidden="true">—</span><span class="screen-reader-text">' .
		       esc_html__( 'Unknown', 'arraypress' ) . '</span>';
	}

	/**
	 * Format email value
	 *
	 * @param string $email Email address
	 *
	 * @return string Email link HTML
	 */
	public static function format_email( string $email ): string {
		return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
	}

	/**
	 * Format date value (UTC to local with human diff)
	 *
	 * @param string $utc_datetime UTC datetime from database
	 *
	 * @return string Formatted date HTML with title
	 */
	public static function format_date( string $utc_datetime ): string {
		if ( Dates::is_zero( $utc_datetime ) ) {
			return self::render_empty();
		}

		$human     = Dates::human_diff( $utc_datetime );
		$formatted = Dates::format( $utc_datetime );

		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $formatted ),
			esc_html( $human )
		);
	}

	/**
	 * Format price value (smallest unit to currency)
	 *
	 * Converts amount stored in smallest unit (cents, pence, etc.) to
	 * formatted currency string with proper symbol and decimals.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value    Amount in smallest unit (e.g., cents).
	 * @param object $item     Data object (checked for currency property/method).
	 * @param string $currency Optional currency code override (default: USD).
	 *
	 * @return string Formatted price HTML.
	 */
	public static function format_price( $value, $item, string $currency = '' ): string {
		$amount = is_numeric( $value ) ? intval( $value ) : 0;

		// Get currency from item if not provided
		if ( empty( $currency ) ) {
			if ( method_exists( $item, 'get_currency' ) ) {
				$currency = $item->get_currency();
			} elseif ( is_object( $item ) && property_exists( $item, 'currency' ) && ! empty( $item->currency ) ) {
				$currency = $item->currency;
			} else {
				$currency = 'USD';
			}
		}

		$formatted = Currency::format( $amount, $currency );

		return sprintf( '<span class="price">%s</span>', esc_html( $formatted ) );
	}

	/**
	 * Format count value
	 *
	 * @param mixed $value Count value
	 *
	 * @return string Formatted count HTML
	 */
	public static function format_count( $value ): string {
		$count = is_numeric( $value ) ? intval( $value ) : 0;

		// -1 means unlimited
		if ( $count === - 1 ) {
			return '<span class="unlimited">∞</span>';
		}

		// Zero shows as empty
		if ( $count === 0 ) {
			return '<span aria-hidden="true">—</span><span class="screen-reader-text">' .
			       esc_html__( 'None', 'arraypress' ) . '</span>';
		}

		return number_format_i18n( $count );
	}

	/**
	 * Format URL value
	 *
	 * @param string $url         URL value
	 * @param string $column_name Column name (to detect image URLs)
	 *
	 * @return string Formatted URL HTML
	 */
	public static function format_url( string $url, string $column_name = '' ): string {
		// Image URL - show thumbnail
		if ( str_contains( $column_name, 'image' ) || str_contains( $column_name, 'avatar' ) ||
		     str_contains( $column_name, 'thumbnail' ) || str_contains( $column_name, 'logo' ) ) {
			return sprintf(
				'<a href="%1$s" target="_blank"><img src="%1$s" style="max-width: 50px; height: auto;" alt="" loading="lazy" /></a>',
				esc_url( $url )
			);
		}

		// Regular URL - show domain
		$display_url = wp_parse_url( $url, PHP_URL_HOST ) ?: $url;

		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $url ),
			esc_html( $display_url )
		);
	}

	/**
	 * Format boolean value
	 *
	 * @param mixed  $value       Boolean value
	 * @param string $column_name Column name (for special handling)
	 *
	 * @return string Formatted boolean HTML
	 */
	public static function format_boolean( $value, string $column_name = '' ): string {
		$is_true = filter_var( $value, FILTER_VALIDATE_BOOLEAN );

		// Special handling for test/live mode
		if ( $column_name === 'is_test' || $column_name === 'test_mode' ) {
			return $is_true
				? StatusBadge::render( 'test', [ 'test' => 'warning' ], [ 'test' => __( 'Test', 'arraypress' ) ] )
				: StatusBadge::render( 'live', [ 'live' => 'success' ], [ 'live' => __( 'Live', 'arraypress' ) ] );
		}

		// Standard boolean icons
		return $is_true
			? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span><span class="screen-reader-text">' . esc_html__( 'Yes', 'arraypress' ) . '</span>'
			: '<span class="dashicons dashicons-minus" style="color: #a7aaad;"></span><span class="screen-reader-text">' . esc_html__( 'No', 'arraypress' ) . '</span>';
	}

	/**
	 * Format country code with flag and name
	 *
	 * Displays a country code as "🇺🇸 United States" format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code         Country code (ISO 3166-1 alpha-2, e.g., 'US', 'GB').
	 * @param bool   $include_flag Include emoji flag (default true).
	 * @param bool   $include_name Include country name (default true).
	 *
	 * @return string Formatted country HTML.
	 */
	public static function format_country( string $code, bool $include_flag = true, bool $include_name = true ): string {
		if ( empty( $code ) ) {
			return self::render_empty();
		}

		$code = strtoupper( trim( $code ) );
		$flag = $include_flag ? Countries::get_flag( $code ) : '';
		$name = $include_name ? Countries::get_name( $code ) : '';

		// If name came back as the code itself, the country wasn't found
		if ( $name === $code && ! Countries::exists( $code ) ) {
			return esc_html( $code );
		}

		$parts = array_filter( [ $flag, $name ] );

		return esc_html( implode( ' ', $parts ) );
	}

	/* ========================================================================
	 * COLUMN TYPE DETECTION
	 * ======================================================================== */

	/**
	 * Check if value is empty
	 *
	 * @param mixed $value Value to check
	 *
	 * @return bool
	 */
	public static function is_empty( $value ): bool {
		return $value === null || $value === '' || $value === false;
	}

	/**
	 * Check if column is an email column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_email_column( string $column_name ): bool {
		return str_contains( $column_name, 'email' );
	}

	/**
	 * Check if column is a country column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_country_column( string $column_name ): bool {
		return $column_name === 'country' ||
		       $column_name === 'country_code' ||
		       str_ends_with( $column_name, '_country' );
	}

	/**
	 * Check if column is a date column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_date_column( string $column_name ): bool {
		// Check exact matches
		if ( in_array( $column_name, self::$date_columns, true ) ) {
			return true;
		}

		// Check patterns
		foreach ( self::$date_patterns as $pattern ) {
			if ( str_contains( $column_name, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if column is a price/money column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_price_column( string $column_name ): bool {
		foreach ( self::$price_patterns as $pattern ) {
			if ( str_contains( $column_name, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if column is a status column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_status_column( string $column_name ): bool {
		return $column_name === 'status' || str_contains( $column_name, '_status' );
	}

	/**
	 * Check if column is a count column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_count_column( string $column_name ): bool {
		foreach ( self::$count_patterns as $pattern ) {
			if ( str_contains( $column_name, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if column is a URL column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_url_column( string $column_name ): bool {
		return str_contains( $column_name, '_url' ) || str_contains( $column_name, 'url' ) ||
		       str_contains( $column_name, 'website' ) || str_contains( $column_name, 'link' );
	}

	/**
	 * Check if column is a boolean column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 */
	public static function is_boolean_column( string $column_name ): bool {
		// Check prefix
		if ( str_starts_with( $column_name, 'is_' ) || str_starts_with( $column_name, 'has_' ) ||
		     str_starts_with( $column_name, 'can_' ) ) {
			return true;
		}

		// Check exact matches
		return in_array( $column_name, self::$boolean_columns, true );
	}

}