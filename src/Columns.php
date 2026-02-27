<?php
/**
 * Column Formatting Utilities
 *
 * Handles automatic formatting of column values based on naming patterns.
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

use ArrayPress\Countries\Countries;
use ArrayPress\Currencies\Currency;
use ArrayPress\DateUtils\Dates;
use ArrayPress\RateFormat\Rate;
use ArrayPress\StatusBadge\StatusBadge;
use ArrayPress\Stripe\Format;
use WP_Term;

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
		'phone'      => [
			'exact'    => [ 'phone', 'mobile', 'cell', 'fax', 'telephone' ],
			'contains' => [ 'phone' ],
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
		'items'      => [
			'exact'  => [ 'items', 'order_items', 'line_items', 'products' ],
			'suffix' => [ '_items' ],
		],
		'customer'   => [
			'exact'  => [ 'customer', 'buyer', 'recipient' ],
			'suffix' => [ '_customer' ],
		],
		'user'       => [
			'exact'  => [ 'user', 'author', 'owner', 'assignee' ],
			'suffix' => [ '_user', '_author' ],
		],
		'taxonomy'   => [
			'exact'  => [ 'terms', 'tags', 'categories', 'taxonomy' ],
			'suffix' => [ '_terms', '_tags', '_categories' ],
		],
		'image'      => [
			'exact'    => [ 'image', 'avatar', 'thumbnail', 'logo', 'photo', 'icon' ],
			'contains' => [ '_image', '_avatar', '_thumbnail', '_logo', '_photo' ],
		],
		'color'      => [
			'exact'  => [ 'color', 'colour' ],
			'suffix' => [ '_color', '_colour' ],
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
	 * Column type is resolved in this order:
	 * 1. Explicit 'type' in column config (highest priority)
	 * 2. Auto-detection from column name patterns
	 *
	 * Set 'type' => false in column config to disable auto-formatting
	 * entirely and output the raw escaped value.
	 *
	 * Every formatter receives a uniform signature of ($value, $item,
	 * $column_name, $config) and extracts what it needs from config
	 * internally (styles, size, decimals, singular, plural, etc.).
	 *
	 * @param string $column_name Column name.
	 * @param mixed  $value       Column value.
	 * @param object $item        Data object.
	 * @param array  $config      Column config from registration. Supports:
	 *                            - 'type'        (string|false) Explicit column type or false to disable.
	 *                            - 'styles'      (array)        Status => badge type mappings.
	 *                            - 'size'        (string|array) Image size name or [w, h] array.
	 *                            - 'decimals'    (int)          Decimal places for file_size.
	 *                            - 'singular'    (string)       Singular label for items type.
	 *                            - 'plural'      (string)       Plural label for items type.
	 *                            - 'taxonomy'    (string)       Taxonomy slug for term links.
	 *                            - 'avatar'      (int)          Avatar size in pixels for user/customer type.
	 *                            - '_filter_url' (string)       Explicit URL override for customer link.
	 *
	 * @return string Formatted HTML.
	 */
	public static function auto_format(
		string $column_name,
		$value,
		$item,
		array $config = []
	): string {
		// Resolve type: explicit config wins, then auto-detect from name
		$type = array_key_exists( 'type', $config )
			? $config['type']
			: self::detect_type( $column_name );

		// Allow disabling auto-format entirely with type => false
		if ( $type === false || $type === null ) {
			return esc_html( (string) $value );
		}

		// Handle empty values (but not for items which can be an empty array)
		if ( $type !== 'items' && self::is_empty( $value ) ) {
			return self::render_empty();
		}

		return match ( $type ) {
			'email'      => self::format_email( $value, $item, $column_name, $config ),
			'phone'      => self::format_phone( $value, $item, $column_name, $config ),
			'country'    => self::format_country( $value, $item, $column_name, $config ),
			'date'       => self::format_date( $value, $item, $column_name, $config ),
			'duration'   => self::format_duration( $value, $item, $column_name, $config ),
			'price'      => self::format_price( $value, $item, $column_name, $config ),
			'rate'       => self::format_rate( $value, $item, $column_name, $config ),
			'percentage' => self::format_percentage( $value, $item, $column_name, $config ),
			'status'     => self::format_status( $value, $item, $column_name, $config ),
			'count'      => self::format_count( $value, $item, $column_name, $config ),
			'items'      => self::format_items( $value, $item, $column_name, $config ),
			'user'       => self::format_user( $value, $item, $column_name, $config ),
			'customer'   => self::format_customer( $value, $item, $column_name, $config ),
			'taxonomy'   => self::format_taxonomy( $value, $item, $column_name, $config ),
			'image'      => self::format_image( $value, $item, $column_name, $config ),
			'color'      => self::format_color( $value, $item, $column_name, $config ),
			'url'        => self::format_url( $value, $item, $column_name, $config ),
			'boolean'    => self::format_boolean( $value, $item, $column_name, $config ),
			'code'       => self::format_code( $value, $item, $column_name, $config ),
			'file_size'  => self::format_file_size( $value, $item, $column_name, $config ),
			'stripe_price' => self::format_stripe_price( $value, $item, $column_name, $config ),
			default      => esc_html( (string) $value ),
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
	 * @param array $styles Custom status => badge type mappings.
	 *
	 * @return StatusBadge
	 */
	private static function get_badge( array $styles = [] ): StatusBadge {
		$key = empty( $styles ) ? '_default' : md5( serialize( $styles ) );

		if ( ! isset( self::$badges[ $key ] ) ) {
			self::$badges[ $key ] = new StatusBadge( $styles );
		}

		return self::$badges[ $key ];
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
					'exact'    => $name === $value,
					'prefix'   => str_starts_with( $name, $value ),
					'suffix'   => str_ends_with( $name, $value ),
					'contains' => str_contains( $name, $value ),
					default    => false,
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
	 * Format an email address as a clickable mailto link
	 *
	 * @param mixed  $value       Email address.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Email link HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_email( $value, $item, string $column_name, array $config = [] ): string {
		$email = (string) $value;

		$output = sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );

		/**
		 * Filter the formatted email column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw email address.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_email', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a phone number as a clickable tel: link
	 *
	 * Strips non-numeric characters (except leading +) for the href
	 * while preserving the original format for display.
	 *
	 * @param mixed  $value       Phone number.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Phone link HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_phone( $value, $item, string $column_name, array $config = [] ): string {
		$phone = (string) $value;
		$tel   = preg_replace( '/[^\d+]/', '', $phone );

		$output = sprintf(
			'<a href="tel:%s">%s</a>',
			esc_attr( $tel ),
			esc_html( $phone )
		);

		/**
		 * Filter the formatted phone column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw phone number.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_phone', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a country code as a flag and country name
	 *
	 * Uses the Countries library to render the country flag emoji
	 * and full country name from a two-letter ISO code.
	 *
	 * @param mixed  $value       Two-letter country code (e.g., 'US', 'GB').
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Country flag and name HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_country( $value, $item, string $column_name, array $config = [] ): string {
		$output = Countries::render( (string) $value ) ?? self::render_empty();

		/**
		 * Filter the formatted country column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw country code.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_country', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a date/datetime value as a human-readable time difference
	 *
	 * Uses the DateUtils library to render dates as relative time
	 * (e.g., "2 hours ago") with the full date shown on hover.
	 *
	 * @param mixed  $value       Date string, timestamp, or DateTime object.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted date HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_date( $value, $item, string $column_name, array $config = [] ): string {
		$output = Dates::render_date( $value ) ?? self::render_empty();

		/**
		 * Filter the formatted date column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw date value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_date', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a duration value in seconds as a human-readable string
	 *
	 * Uses the DateUtils library to render durations like "2h 15m" or "3 days".
	 *
	 * @param mixed  $value       Duration in seconds.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted duration HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_duration( $value, $item, string $column_name, array $config = [] ): string {
		$output = Dates::render_duration( $value ) ?? self::render_empty();

		/**
		 * Filter the formatted duration column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw duration value in seconds.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_duration', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a monetary amount with currency symbol
	 *
	 * Resolves the currency code from the data object by checking for
	 * common getter methods (get_currency, get_currency_code), then
	 * falls back to config['currency'] or 'USD' as a last resort.
	 *
	 * Does not auto-resolve recurring intervals since the price type
	 * matches many column names (revenue, total_spent, balance, cost)
	 * where interval display would be incorrect.
	 *
	 * @param mixed  $value       Amount in smallest currency unit (e.g., cents).
	 * @param object $item        Data object (checked for currency getter methods).
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'currency' (string) Explicit currency code override.
	 *
	 * @return string Formatted price HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_price( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		// Resolve currency: config override, then item methods, then default
		$currency = $config['currency']
		            ?? self::resolve_method( $item, [ 'get_currency', 'get_currency_code' ] )
		               ?? 'USD';

		$formatted = Currency::format( intval( $value ), $currency );

		$output = sprintf( '<span class="price">%s</span>', esc_html( $formatted ) );

		/**
		 * Filter the formatted price column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw amount in smallest currency unit.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 * @param string $currency    Resolved currency code.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_price', $output, $value, $item, $column_name, $config, $currency );
	}

	/**
	 * Format a Stripe price amount with currency and optional recurring interval
	 *
	 * Uses the Stripe Format library to render prices with billing interval
	 * text (e.g., "$9.99 per month", "$29.99 every 3 months"). Resolves
	 * currency and interval data from the item object automatically.
	 *
	 * This type is not auto-detected from column names — use 'type' => 'stripe_price'
	 * in column config to enable it.
	 *
	 * Requires the arraypress/stripe library. Falls back to format_price()
	 * if the Format class is unavailable.
	 *
	 * @param mixed  $value       Amount in smallest currency unit (e.g., cents).
	 * @param object $item        Data object (checked for currency and interval methods).
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'currency'       (string) Explicit currency code override.
	 *                            - 'interval'       (string) Explicit interval override.
	 *                            - 'interval_count' (int)    Explicit interval count override.
	 *
	 * @return string Formatted price HTML with optional interval, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_stripe_price( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		// Resolve currency
		$currency = $config['currency']
		            ?? self::resolve_method( $item, [ 'get_currency', 'get_currency_code' ] )
		               ?? 'USD';

		// Resolve interval
		$interval = $config['interval']
		            ?? self::resolve_method( $item, [ 'get_interval', 'get_billing_interval' ] );

		// Resolve interval count
		$interval_count = $config['interval_count'] ?? null;
		if ( $interval_count === null ) {
			$resolved = self::resolve_method( $item, [ 'get_interval_count', 'get_billing_interval_count' ] );
			$interval_count = $resolved !== null ? (int) $resolved : 1;
		}

		$formatted = Format::price_with_interval(
			intval( $value ),
			$currency,
			$interval,
			$interval_count
		);

		$output = sprintf( '<span class="price">%s</span>', esc_html( $formatted ) );

		/**
		 * Filter the formatted Stripe price column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw amount in smallest currency unit.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 * @param string $currency    Resolved currency code.
		 * @param string|null $interval       Resolved billing interval.
		 * @param int    $interval_count Resolved interval count.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_stripe_price', $output, $value, $item, $column_name, $config, $currency, $interval, $interval_count );
	}

	/**
	 * Format a rate value (e.g., discount rate, commission rate)
	 *
	 * Uses the RateFormat library to render rate values with appropriate
	 * formatting based on the column context and item data.
	 *
	 * @param mixed  $value       Rate value.
	 * @param object $item        Data object for context.
	 * @param string $column_name Column name for context-aware formatting.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted rate HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_rate( $value, $item, string $column_name, array $config = [] ): string {
		$output = Rate::render( $value, $item, $column_name ) ?? self::render_empty();

		/**
		 * Filter the formatted rate column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw rate value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_rate', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a percentage value
	 *
	 * Uses the RateFormat library to render a numeric value as a percentage
	 * with the appropriate symbol and formatting.
	 *
	 * @param mixed  $value       Percentage value (e.g., 25, 99.5).
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted percentage HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_percentage( $value, $item, string $column_name, array $config = [] ): string {
		$output = Rate::render_percentage( $value ) ?? self::render_empty();

		/**
		 * Filter the formatted percentage column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw percentage value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_percentage', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a status value as a styled badge
	 *
	 * Renders the status as a colored badge using the StatusBadge library.
	 * Custom style mappings can be provided via config['styles'] to control
	 * badge appearance per status value.
	 *
	 * @param mixed  $value       Status string (e.g., 'active', 'pending').
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'styles' (array) Status => badge type mappings.
	 *
	 * @return string Badge HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_status( $value, $item, string $column_name, array $config = [] ): string {
		$styles = $config['styles'] ?? [];

		$output = self::get_badge( $styles )->render( (string) $value );

		/**
		 * Filter the formatted status column output
		 *
		 * @param string $output      Formatted badge HTML.
		 * @param mixed  $value       Raw status value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_status', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a count value with special handling for unlimited (-1) and zero
	 *
	 * Renders -1 as an infinity symbol (∞), zero as an empty placeholder,
	 * and positive values as locale-formatted numbers.
	 *
	 * @param mixed  $value       Count value.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted count HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_count( $value, $item, string $column_name, array $config = [] ): string {
		$count = is_numeric( $value ) ? intval( $value ) : 0;

		if ( $count === - 1 ) {
			$output = '<span class="unlimited">∞</span>';
		} elseif ( $count === 0 ) {
			$output = '<span aria-hidden="true">—</span><span class="screen-reader-text">' .
			          esc_html__( 'None', 'arraypress' ) . '</span>';
		} else {
			$output = number_format_i18n( $count );
		}

		/**
		 * Filter the formatted count column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw count value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_count', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format an items summary from an array of names or a numeric count
	 *
	 * Arrays render as:
	 * - 1 item:   "Item Name"
	 * - 2 items:  "Item Name and 1 other item"
	 * - 3+ items: "Item Name and 2 other items"
	 *
	 * Numeric values render as "4 items".
	 *
	 * Labels are extracted from config['singular'] and config['plural'],
	 * defaulting to "item" and "items" respectively.
	 *
	 * @param mixed  $value       Array of item names/objects or numeric count.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'singular' (string) Singular label (default: "item").
	 *                            - 'plural'   (string) Plural label (default: "items").
	 *
	 * @return string Formatted items HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_items( $value, $item, string $column_name, array $config = [] ): string {
		$singular = $config['singular'] ?? __( 'item', 'arraypress' );
		$plural   = $config['plural'] ?? __( 'items', 'arraypress' );

		// Numeric count
		if ( is_numeric( $value ) ) {
			$count = (int) $value;

			if ( $count === 0 ) {
				return self::render_empty();
			}

			$output = sprintf(
				'<span class="column-items">%s %s</span>',
				esc_html( number_format_i18n( $count ) ),
				esc_html( $count === 1 ? $singular : $plural )
			);
		} elseif ( is_array( $value ) ) {
			$names = array_filter( array_map( function ( $entry ) {
				return is_object( $entry ) && isset( $entry->name )
					? $entry->name
					: (string) $entry;
			}, $value ) );

			$count = count( $names );

			if ( $count === 0 ) {
				return self::render_empty();
			}

			$first = esc_html( $names[0] );
			$rest  = $count - 1;

			if ( $rest === 0 ) {
				$output = sprintf( '<span class="column-items">%s</span>', $first );
			} else {
				$output = sprintf(
					'<span class="column-items">%s <span class="column-items-rest">%s %s %s</span></span>',
					$first,
					esc_html__( 'and', 'arraypress' ),
					esc_html( number_format_i18n( $rest ) ),
					esc_html( sprintf(
						_n( 'other %s', 'other %s', $rest, 'arraypress' ),
						$rest === 1 ? $singular : $plural
					) )
				);
			}
		} else {
			return self::render_empty();
		}

		/**
		 * Filter the formatted items column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw value (array or numeric).
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_items', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a user ID as an avatar with linked display name
	 *
	 * Renders a WordPress user's avatar image alongside their display name,
	 * linked to the user's profile edit screen.
	 *
	 * Avatar size is extracted from config['avatar'], defaulting to 32px.
	 *
	 * @param mixed  $value       User ID.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'avatar' (int) Avatar size in pixels (default: 32).
	 *
	 * @return string Formatted user HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_user( $value, $item, string $column_name, array $config = [] ): string {
		$avatar_size = $config['avatar'] ?? 32;
		$user_id     = (int) $value;

		if ( $user_id < 1 ) {
			return self::render_empty();
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return self::render_empty();
		}

		$name       = esc_html( $user->display_name );
		$avatar_img = get_avatar( $user_id, $avatar_size );
		$edit_url   = get_edit_user_link( $user_id );

		$name_html = $edit_url
			? sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), $name )
			: $name;

		$output = sprintf(
			'<span class="column-user">%s %s</span>',
			$avatar_img,
			$name_html
		);

		/**
		 * Filter the formatted user column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw user ID.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_user', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a customer/person value with avatar, name, and email
	 *
	 * Inspects the data object for common getter methods to build
	 * a rich display with avatar, name, email, and optional link.
	 * Works with any object that exposes some combination of:
	 *
	 * - get_email() or get_customer_email()
	 * - get_name() or get_display_name() or get_customer_name()
	 * - get_url() or get_edit_url() or get_admin_url()
	 *
	 * Falls back gracefully when methods are missing. If the value
	 * is a numeric user ID, delegates to format_user() instead.
	 *
	 * @param mixed  $value       Object, user ID, or customer ID.
	 * @param object $item        The row data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'avatar'      (int)    Avatar size in pixels (default: 32).
	 *                            - '_filter_url' (string) Explicit URL override for name link.
	 *
	 * @return string Formatted customer HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_customer( $value, $item, string $column_name, array $config = [] ): string {
		$avatar_size = $config['avatar'] ?? 32;

		// If it's a numeric ID, try the item itself for customer methods
		$source = is_object( $value ) ? $value : $item;

		// Resolve email
		$email = self::resolve_method( $source, [
			'get_email',
			'get_customer_email',
		] );

		// Resolve name
		$name = self::resolve_method( $source, [
			'get_display_name',
			'get_name',
			'get_customer_name',
		] );

		// Resolve URL: explicit filter URL wins, then method resolution
		$url = $config['_filter_url'] ?? self::resolve_method( $source, [
			'get_edit_url',
			'get_admin_url',
			'get_admin_orders_url',
			'get_url',
		] );

		// If we have nothing to work with, fall back to user formatter
		if ( empty( $email ) && empty( $name ) ) {
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				return self::format_user( $value, $item, $column_name, $config );
			}

			return self::render_empty();
		}

		// Build avatar from email
		$avatar_html = '';
		if ( ! empty( $email ) ) {
			$avatar_html = get_avatar( $email, $avatar_size );
		}

		// Fall back name to email local part
		if ( empty( $name ) && ! empty( $email ) ) {
			$name = strstr( $email, '@', true );
		}

		// Build name HTML with optional link
		$name_escaped = esc_html( $name );
		$name_html    = ! empty( $url )
			? sprintf( '<a href="%s">%s</a>', esc_url( $url ), $name_escaped )
			: $name_escaped;

		// Build email subtitle
		$email_html = '';
		if ( ! empty( $email ) ) {
			$email_html = sprintf(
				'<span class="column-customer-email">%s</span>',
				esc_html( $email )
			);
		}

		$output = sprintf(
			'<span class="column-customer">%s<span class="column-customer-detail">%s%s</span></span>',
			$avatar_html,
			$name_html,
			$email_html
		);

		/**
		 * Filter the formatted customer column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw value (object or ID).
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_customer', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Resolve the first available method on an object
	 *
	 * Iterates through method names and returns the result of the
	 * first one that exists and returns a non-empty value.
	 *
	 * @param object   $object  Object to check.
	 * @param string[] $methods Method names to try in order.
	 *
	 * @return string|null First non-empty result or null.
	 */
	private static function resolve_method( $object, array $methods ): ?string {
		if ( ! is_object( $object ) ) {
			return null;
		}

		foreach ( $methods as $method ) {
			if ( method_exists( $object, $method ) ) {
				$result = $object->$method();

				if ( ! empty( $result ) ) {
					return (string) $result;
				}
			}
		}

		return null;
	}

	/**
	 * Format taxonomy terms as linked badges
	 *
	 * Accepts an array of term names, term objects, or WP_Term objects.
	 * When config['taxonomy'] is provided, term names link to their
	 * admin edit screens.
	 *
	 * @param mixed  $value       Array of term names, term objects, or WP_Term objects.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'taxonomy' (string) Taxonomy slug for admin links.
	 *
	 * @return string Formatted terms HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_taxonomy( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_array( $value ) ) {
			return self::render_empty();
		}

		$taxonomy = $config['taxonomy'] ?? null;
		$terms    = [];

		foreach ( $value as $term ) {
			$name    = null;
			$term_id = null;

			if ( $term instanceof WP_Term ) {
				$name    = $term->name;
				$term_id = $term->term_id;
			} elseif ( is_object( $term ) && isset( $term->name ) ) {
				$name    = $term->name;
				$term_id = $term->term_id ?? null;
			} elseif ( is_string( $term ) ) {
				$name = $term;
			}

			if ( empty( $name ) ) {
				continue;
			}

			// Link to term admin page if taxonomy is known and we have an ID
			if ( $taxonomy && $term_id ) {
				$edit_url = get_edit_term_link( $term_id, $taxonomy );

				if ( $edit_url ) {
					$terms[] = sprintf(
						'<a href="%s" class="column-term">%s</a>',
						esc_url( $edit_url ),
						esc_html( $name )
					);
					continue;
				}
			}

			$terms[] = sprintf( '<span class="column-term">%s</span>', esc_html( $name ) );
		}

		if ( empty( $terms ) ) {
			return self::render_empty();
		}

		$output = sprintf( '<span class="column-taxonomy">%s</span>', implode( ', ', $terms ) );

		/**
		 * Filter the formatted taxonomy column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw term data.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_taxonomy', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a URL as a clickable link showing the hostname
	 *
	 * Extracts the hostname from the URL for a clean display while
	 * linking to the full URL in a new tab.
	 *
	 * @param mixed  $value       URL value.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted URL HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_url( $value, $item, string $column_name, array $config = [] ): string {
		$url         = (string) $value;
		$display_url = wp_parse_url( $url, PHP_URL_HOST ) ?: $url;

		$output = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $url ),
			esc_html( $display_url )
		);

		/**
		 * Filter the formatted URL column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw URL.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_url', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format an image value as a thumbnail
	 *
	 * Accepts either a WordPress attachment ID or a raw URL. Attachment IDs
	 * use wp_get_attachment_image() for proper srcset and responsive handling.
	 * Attachment thumbnails are wrapped in a link to the full-size image.
	 *
	 * Image size is extracted from config['size'], defaulting to 'thumbnail'.
	 *
	 * @param mixed  $value       Attachment ID (int) or image URL (string).
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'size' (string|array) WordPress image size or [w, h] array.
	 *
	 * @return string Formatted image HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_image( $value, $item, string $column_name, array $config = [] ): string {
		$size = $config['size'] ?? 'thumbnail';

		if ( is_numeric( $value ) ) {
			$image = wp_get_attachment_image( (int) $value, $size, false, [
				'class'   => 'column-thumbnail',
				'loading' => 'lazy',
			] );

			if ( $image ) {
				$full_url = wp_get_attachment_url( (int) $value );

				$output = $full_url
					? sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $full_url ), $image )
					: $image;
			} else {
				$output = self::render_empty();
			}
		} else {
			// Raw URL fallback
			$output = sprintf(
				'<img src="%s" class="column-thumbnail" alt="" loading="lazy" />',
				esc_url( (string) $value )
			);
		}

		/**
		 * Filter the formatted image column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw value (attachment ID or URL).
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_image', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a color value as a swatch with hex/rgb code
	 *
	 * Renders a small color swatch alongside the hex/rgb value in
	 * monospace font.
	 *
	 * @param mixed  $value       Color value (hex, rgb, or any valid CSS color).
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted color HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_color( $value, $item, string $column_name, array $config = [] ): string {
		$color = (string) $value;

		if ( empty( $color ) ) {
			return self::render_empty();
		}

		$output = sprintf(
			'<span class="column-color"><span class="column-color-swatch" style="background-color:%s;"></span><code class="code">%s</code></span>',
			esc_attr( $color ),
			esc_html( $color )
		);

		/**
		 * Filter the formatted color column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw color value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_color', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a boolean value as a yes/no icon or test/live badge
	 *
	 * Standard booleans render as checkmark (yes) or dash (no) icons.
	 * Special handling is provided for test mode columns (is_test,
	 * test_mode) which render as "Test" or "Live" badges.
	 *
	 * Badge styles can be customized via config['styles'].
	 *
	 * @param mixed  $value       Boolean value (truthy/falsy).
	 * @param object $item        Data object.
	 * @param string $column_name Column name (for special handling).
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'styles' (array) Status => badge type mappings.
	 *
	 * @return string Formatted boolean HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_boolean( $value, $item, string $column_name, array $config = [] ): string {
		$is_true = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		$styles  = $config['styles'] ?? [];

		// Special handling for test/live mode
		if ( $column_name === 'is_test' || $column_name === 'test_mode' ) {
			$badge = self::get_badge( $styles );

			$output = $is_true
				? $badge->render( 'test', StatusBadge::WARNING, __( 'Test', 'arraypress' ) )
				: $badge->render( 'live', StatusBadge::SUCCESS, __( 'Live', 'arraypress' ) );
		} else {
			// Standard boolean icons
			$output = $is_true
				? '<span class="dashicons dashicons-yes-alt column-boolean-yes"></span><span class="screen-reader-text">' . esc_html__( 'Yes', 'arraypress' ) . '</span>'
				: '<span class="dashicons dashicons-minus column-boolean-no"></span><span class="screen-reader-text">' . esc_html__( 'No', 'arraypress' ) . '</span>';
		}

		/**
		 * Filter the formatted boolean column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw boolean value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_boolean', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a code/ID value in monospace font
	 *
	 * Displays codes, IDs, UUIDs, SKUs, tokens, etc. in a styled
	 * monospace code element for readability.
	 *
	 * @param mixed  $value       Code value.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string Formatted code HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_code( $value, $item, string $column_name, array $config = [] ): string {
		$code = (string) $value;

		if ( empty( $code ) ) {
			return self::render_empty();
		}

		$output = sprintf( '<code class="code">%s</code>', esc_html( $code ) );

		/**
		 * Filter the formatted code column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw code value.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_code', $output, $value, $item, $column_name, $config );
	}

	/**
	 * Format a file size in bytes as a human-readable string
	 *
	 * Converts bytes into human-readable file size format (e.g., "1.5 MB")
	 * using WordPress's size_format() function.
	 *
	 * Decimal places are extracted from config['decimals'], defaulting to 1.
	 *
	 * @param mixed  $value       Size in bytes.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'decimals' (int) Decimal places (default: 1).
	 *
	 * @return string Formatted file size HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_file_size( $value, $item, string $column_name, array $config = [] ): string {
		$decimals = $config['decimals'] ?? 1;

		if ( ! is_numeric( $value ) || $value < 0 ) {
			return self::render_empty();
		}

		$formatted = size_format( (int) $value, $decimals );

		$output = sprintf( '<span class="file-size">%s</span>', esc_html( $formatted ) );

		/**
		 * Filter the formatted file size column output
		 *
		 * @param string $output      Formatted HTML.
		 * @param mixed  $value       Raw size in bytes.
		 * @param object $item        Data object.
		 * @param string $column_name Column name.
		 * @param array  $config      Column configuration.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'arraypress_column_format_file_size', $output, $value, $item, $column_name, $config );
	}

}