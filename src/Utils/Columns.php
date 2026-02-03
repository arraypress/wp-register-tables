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

		// Code/ID columns
		if ( self::is_code_column( $column_name ) ) {
			return self::format_code( $value );
		}

		// Percentage columns
		if ( self::is_percentage_column( $column_name ) ) {
			return self::format_percentage( $value );
		}

		// Rate columns (percentage or flat amount)
		if ( self::is_rate_column( $column_name ) ) {
			return self::format_rate( $value, $item, $column_name );
		}

		// Duration columns
		if ( self::is_duration_column( $column_name ) ) {
			return self::format_duration( $value );
		}

		// File size columns
		if ( self::is_file_size_column( $column_name ) ) {
			return self::format_file_size( $value );
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
	 * @param string $utc_datetime UTC datetime from database.
	 *
	 * @return string Formatted date HTML with title.
	 * @since 1.0.0
	 *
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
	 * @param mixed  $value    Amount in smallest unit (e.g., cents).
	 * @param object $item     Data object (checked for currency property/method).
	 * @param string $currency Optional currency code override (default: USD).
	 *
	 * @return string Formatted price HTML.
	 * @since 1.0.0
	 *
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
		// Image URL — show thumbnail
		if ( str_contains( $column_name, 'image' ) || str_contains( $column_name, 'avatar' ) ||
		     str_contains( $column_name, 'thumbnail' ) || str_contains( $column_name, 'logo' ) ) {
			return sprintf(
				'<a href="%1$s" target="_blank"><img src="%1$s" style="max-width: 50px; height: auto;" alt="" loading="lazy" /></a>',
				esc_url( $url )
			);
		}

		// Regular URL — show domain
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
	 * @param string $code         Country code (ISO 3166-1 alpha-2, e.g., 'US', 'GB').
	 * @param bool   $include_flag Include emoji flag (default true).
	 * @param bool   $include_name Include country name (default true).
	 *
	 * @return string Formatted country HTML.
	 * @since 1.0.0
	 *
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

	/**
	 * Format code/ID value in monospace
	 *
	 * Displays codes, IDs, UUIDs, SKUs etc. in a monospace font for readability.
	 *
	 * @param string $value Code value.
	 *
	 * @return string Formatted code HTML.
	 * @since 1.0.0
	 *
	 */
	public static function format_code( string $value ): string {
		if ( empty( $value ) ) {
			return self::render_empty();
		}

		return sprintf( '<code class="code">%s</code>', esc_html( $value ) );
	}

	/**
	 * Format percentage value
	 *
	 * Displays a numeric value as a percentage. Assumes value is stored
	 * as a whole number (e.g., 15 for 15%, not 0.15).
	 *
	 * @param mixed $value    Percentage value (e.g., 15 for 15%).
	 * @param int   $decimals Number of decimal places (default 0).
	 *
	 * @return string Formatted percentage HTML.
	 * @since 1.0.0
	 *
	 */
	public static function format_percentage( $value, int $decimals = 0 ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		$formatted = number_format_i18n( (float) $value, $decimals );

		return sprintf( '<span class="percentage">%s%%</span>', esc_html( $formatted ) );
	}

	/**
	 * Format rate value (percentage or flat amount)
	 *
	 * Intelligently formats a rate based on its type. Checks for a corresponding
	 * `{column}_type` or `type` property on the item to determine format.
	 *
	 * Types:
	 * - 'percent', 'percentage', '%' → Shows as percentage (e.g., "15%")
	 * - 'flat', 'fixed', 'amount' → Shows as currency (e.g., "$15.00")
	 *
	 * @param mixed  $value       Rate value (in smallest unit for currency, whole number for percent).
	 * @param object $item        Data object (checked for type and currency properties).
	 * @param string $column_name Column name (used to find {column}_type property).
	 *
	 * @return string Formatted rate HTML.
	 * @since 1.0.0
	 *
	 */
	public static function format_rate( $value, $item, string $column_name = 'rate' ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		// Determine rate type from item
		$type = self::get_rate_type( $item, $column_name );

		// Percentage types
		if ( in_array( $type, [ 'percent', 'percentage', '%' ], true ) ) {
			return self::format_percentage( $value );
		}

		// Flat/fixed amount types — treat as currency
		if ( in_array( $type, [ 'flat', 'fixed', 'amount' ], true ) ) {
			return self::format_price( $value, $item );
		}

		// Default: try to guess based on value
		if ( $value <= 100 && $value >= 0 ) {
			return self::format_percentage( $value );
		}

		// Otherwise treat as currency
		return self::format_price( $value, $item );
	}

	/**
	 * Get rate type from item object
	 *
	 * Checks multiple property patterns to find the rate type.
	 *
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 *
	 * @return string|null Rate type or null if not found.
	 * @since 1.0.0
	 *
	 */
	private static function get_rate_type( $item, string $column_name ): ?string {
		// Check for {column}_type property (e.g., rate_type, discount_type)
		$type_property = $column_name . '_type';

		if ( method_exists( $item, 'get_' . $type_property ) ) {
			return call_user_func( [ $item, 'get_' . $type_property ] );
		}

		if ( is_object( $item ) && property_exists( $item, $type_property ) ) {
			return $item->$type_property;
		}

		// Check for generic 'type' property
		if ( method_exists( $item, 'get_type' ) ) {
			return $item->get_type();
		}

		if ( is_object( $item ) && property_exists( $item, 'type' ) ) {
			return $item->type;
		}

		return null;
	}

	/**
	 * Format duration value
	 *
	 * Converts seconds into human-readable duration format using DateUtils.
	 *
	 * @param mixed  $value  Duration in seconds.
	 * @param string $format Format style: 'short' (2h 15m), 'long' (2 hours, 15 minutes), 'compact' (2:15:00).
	 *
	 * @return string Formatted duration HTML.
	 * @since 1.0.0
	 *
	 */
	public static function format_duration( $value, string $format = 'short' ): string {
		if ( ! is_numeric( $value ) || $value < 0 ) {
			return self::render_empty();
		}

		$formatted = Dates::format_duration( (int) $value, $format );

		return sprintf( '<span class="duration">%s</span>', esc_html( $formatted ) );
	}

	/**
	 * Format file size value
	 *
	 * Converts bytes into human-readable file size format using WordPress's size_format().
	 *
	 * @param mixed $value    Size in bytes.
	 * @param int   $decimals Number of decimal places (default 1).
	 *
	 * @return string Formatted file size HTML.
	 * @since 1.0.0
	 *
	 */
	public static function format_file_size( $value, int $decimals = 1 ): string {
		if ( ! is_numeric( $value ) || $value < 0 ) {
			return self::render_empty();
		}

		$formatted = size_format( (int) $value, $decimals );

		return sprintf( '<span class="file-size">%s</span>', esc_html( $formatted ) );
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

	/**
	 * Check if column is a code/ID column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public static function is_code_column( string $column_name ): bool {
		return str_ends_with( $column_name, '_code' ) ||
		       str_ends_with( $column_name, '_id' ) ||
		       str_ends_with( $column_name, '_key' ) ||
		       str_ends_with( $column_name, '_token' ) ||
		       $column_name === 'code' ||
		       $column_name === 'sku' ||
		       $column_name === 'uuid' ||
		       $column_name === 'guid' ||
		       $column_name === 'hash' ||
		       $column_name === 'key' ||
		       $column_name === 'token' ||
		       $column_name === 'reference';
	}

	/**
	 * Check if column is a percentage column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public static function is_percentage_column( string $column_name ): bool {
		return str_contains( $column_name, 'percent' ) ||
		       str_ends_with( $column_name, '_pct' );
	}

	/**
	 * Check if column is a rate column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public static function is_rate_column( string $column_name ): bool {
		return $column_name === 'rate' ||
		       str_ends_with( $column_name, '_rate' ) ||
		       $column_name === 'discount' ||
		       $column_name === 'commission' ||
		       $column_name === 'markup';
	}

	/**
	 * Check if column is a duration column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public static function is_duration_column( string $column_name ): bool {
		return str_contains( $column_name, 'duration' ) ||
		       str_ends_with( $column_name, '_seconds' ) ||
		       str_ends_with( $column_name, '_time' ) ||
		       $column_name === 'elapsed' ||
		       $column_name === 'runtime' ||
		       $column_name === 'length';
	}

	/**
	 * Check if column is a file size column
	 *
	 * @param string $column_name Column name
	 *
	 * @return bool
	 * @since 1.0.0
	 *
	 */
	public static function is_file_size_column( string $column_name ): bool {
		return str_contains( $column_name, 'filesize' ) ||
		       str_contains( $column_name, 'file_size' ) ||
		       str_ends_with( $column_name, '_size' ) ||
		       str_ends_with( $column_name, '_bytes' ) ||
		       $column_name === 'size' ||
		       $column_name === 'bytes';
	}

}