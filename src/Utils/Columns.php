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
use ArrayPress\StatusBadge\StatusBadge;

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
	 * Cached StatusBadge instances keyed by their config hash
	 *
	 * @since 1.0.0
	 * @var array<string, StatusBadge>
	 */
	private static array $badges = [];

	/**
	 * Column type registry
	 *
	 * Maps column types to their detection rules. Each type has one or more
	 * match strategies: exact, prefix, suffix, contains.
	 *
	 * @var array<string, array<string, string[]>>
	 */
	private static array $column_types = [
		'email'      => [
			'contains' => [ 'email' ],
		],
		'country'    => [
			'exact'  => [ 'country', 'country_code' ],
			'suffix' => [ '_country' ],
		],
		'date'       => [
			'exact'    => [
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
			],
			'contains' => [ '_at', 'date' ],
		],
		'price'      => [
			'contains' => [
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
			],
		],
		'status'     => [
			'exact'    => [ 'status' ],
			'contains' => [ '_status' ],
		],
		'count'      => [
			'exact'    => [ 'count', 'limit', 'quantity', 'qty' ],
			'contains' => [ '_count' ],
		],
		'url'        => [
			'exact'    => [ 'url', 'website', 'link' ],
			'contains' => [ '_url', 'website', 'link' ],
		],
		'boolean'    => [
			'exact'  => [
				'test',
				'active',
				'enabled',
				'verified',
				'featured',
				'published',
			],
			'prefix' => [ 'is_', 'has_', 'can_' ],
		],
		'code'       => [
			'exact'  => [ 'code', 'sku', 'uuid', 'guid', 'hash', 'key', 'token', 'reference' ],
			'suffix' => [ '_code', '_id', '_key', '_token' ],
		],
		'percentage' => [
			'contains' => [ 'percent' ],
			'suffix'   => [ '_pct' ],
		],
		'rate'       => [
			'exact'  => [ 'rate', 'discount', 'commission', 'markup' ],
			'suffix' => [ '_rate' ],
		],
		'duration'   => [
			'exact'    => [ 'elapsed', 'runtime', 'length' ],
			'contains' => [ 'duration' ],
			'suffix'   => [ '_seconds', '_time' ],
		],
		'file_size'  => [
			'exact'    => [ 'size', 'bytes' ],
			'contains' => [ 'filesize', 'file_size' ],
			'suffix'   => [ '_size', '_bytes' ],
		],
	];

	/**
	 * Auto-format a column value based on column name patterns
	 *
	 * @param string $column_name   Column name.
	 * @param mixed  $value         Column value.
	 * @param object $item          Data object.
	 * @param array  $status_styles Custom status => badge type mappings (e.g., ['active' => 'success']).
	 * @param array  $views         View/label mappings for statuses (e.g., ['active' => 'Active']).
	 *
	 * @return string Formatted HTML.
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

		$type = self::detect_type( $column_name );

		return match ( $type ) {
			'email' => self::format_email( $value ),
			'country' => self::format_country( $value ),
			'date' => Dates::render_date( $value ) ?? self::render_empty(),
			'duration' => Dates::render_duration( $value ) ?? self::render_empty(),
			'price' => self::format_price( $value, $item ),
			'status' => self::format_status( $value, $status_styles ),
			'count' => self::format_count( $value ),
			'url' => self::format_url( $value, $column_name ),
			'boolean' => self::format_boolean( $value, $column_name, $status_styles ),
			'code' => self::format_code( $value ),
			'percentage' => self::format_percentage( $value ),
			'rate' => self::format_rate( $value, $item, $column_name ),
			'file_size' => self::format_file_size( $value ),
			default => esc_html( (string) $value ),
		};
	}

	/* ========================================================================
	 * STATUS BADGE
	 * ======================================================================== */

	/**
	 * Get or create a StatusBadge instance for the given styles
	 *
	 * Caches instances so the same config doesn't create duplicate objects.
	 *
	 * @param array $custom_styles Custom status => badge type mappings.
	 *
	 * @return StatusBadge
	 */
	private static function get_badge( array $custom_styles = [] ): StatusBadge {
		$key = empty( $custom_styles ) ? '_default' : md5( serialize( $custom_styles ) );

		if ( ! isset( self::$badges[ $key ] ) ) {
			self::$badges[ $key ] = new StatusBadge( $custom_styles );
		}

		return self::$badges[ $key ];
	}

	/**
	 * Format a status value as a badge
	 *
	 * @param string $value         Status string.
	 * @param array  $custom_styles Custom status => badge type mappings.
	 *
	 * @return string Badge HTML.
	 */
	public static function format_status( string $value, array $custom_styles = [] ): string {
		return self::get_badge( $custom_styles )->render( $value );
	}

	/* ========================================================================
	 * COLUMN TYPE DETECTION
	 * ======================================================================== */

	/**
	 * Detect the column type from its name
	 *
	 * Checks the column name against the registry of type rules and returns
	 * the first matching type, or null if no match is found.
	 *
	 * @param string $column_name Column name.
	 *
	 * @return string|null Column type or null.
	 */
	public static function detect_type( string $column_name ): ?string {
		foreach ( self::$column_types as $type => $rules ) {
			if ( self::matches_rules( $column_name, $rules ) ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * Check if a column name matches a set of rules
	 *
	 * @param string $name  Column name.
	 * @param array  $rules Match rules (exact, prefix, suffix, contains).
	 *
	 * @return bool
	 */
	private static function matches_rules( string $name, array $rules ): bool {
		foreach ( $rules as $match_type => $values ) {
			foreach ( $values as $value ) {
				$matched = match ( $match_type ) {
					'exact' => $name === $value,
					'prefix' => str_starts_with( $name, $value ),
					'suffix' => str_ends_with( $name, $value ),
					'contains' => str_contains( $name, $value ),
					default => false,
				};

				if ( $matched ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a column is a specific type
	 *
	 * Convenience method for checking column types externally.
	 *
	 * @param string $column_name Column name.
	 * @param string $type        Type to check against.
	 *
	 * @return bool
	 */
	public static function is_type( string $column_name, string $type ): bool {
		return self::detect_type( $column_name ) === $type;
	}

	/**
	 * Check if value is empty
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 */
	public static function is_empty( $value ): bool {
		return $value === null || $value === '' || $value === false;
	}

	/* ========================================================================
	 * FORMATTERS
	 * ======================================================================== */

	/**
	 * Format empty value
	 *
	 * @return string Empty placeholder HTML.
	 */
	public static function render_empty(): string {
		return '<span aria-hidden="true">—</span><span class="screen-reader-text">' .
		       esc_html__( 'Unknown', 'arraypress' ) . '</span>';
	}

	/**
	 * Format email value
	 *
	 * @param string $email Email address.
	 *
	 * @return string Email link HTML.
	 */
	public static function format_email( string $email ): string {
		return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
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
	 * @param mixed $value Count value.
	 *
	 * @return string Formatted count HTML.
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
	 * @param string $url         URL value.
	 * @param string $column_name Column name (to detect image URLs).
	 *
	 * @return string Formatted URL HTML.
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
	 * @param mixed  $value         Boolean value.
	 * @param string $column_name   Column name (for special handling).
	 * @param array  $custom_styles Custom status => badge type mappings.
	 *
	 * @return string Formatted boolean HTML.
	 */
	public static function format_boolean( $value, string $column_name = '', array $custom_styles = [] ): string {
		$is_true = filter_var( $value, FILTER_VALIDATE_BOOLEAN );

		// Special handling for test/live mode
		if ( $column_name === 'is_test' || $column_name === 'test_mode' ) {
			$badge = self::get_badge( $custom_styles );

			return $is_true
				? $badge->render( 'test', StatusBadge::WARNING, __( 'Test', 'arraypress' ) )
				: $badge->render( 'live', StatusBadge::SUCCESS, __( 'Live', 'arraypress' ) );
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
	 * Format file size value
	 *
	 * Converts bytes into human-readable file size format using WordPress's size_format().
	 *
	 * @param mixed $value    Size in bytes.
	 * @param int   $decimals Number of decimal places (default 1).
	 *
	 * @return string Formatted file size HTML.
	 */
	public static function format_file_size( $value, int $decimals = 1 ): string {
		if ( ! is_numeric( $value ) || $value < 0 ) {
			return self::render_empty();
		}

		$formatted = size_format( (int) $value, $decimals );

		return sprintf( '<span class="file-size">%s</span>', esc_html( $formatted ) );
	}

}