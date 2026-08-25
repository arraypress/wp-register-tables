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

use ArrayPress\FieldKit\Support\Display;
use ArrayPress\StatusBadge\StatusBadge;
use ArrayPress\RegisterTables\Traits\DateFormatters;
use ArrayPress\RegisterTables\Traits\MoneyFormatters;
use ArrayPress\RegisterTables\Traits\RelationFormatters;
use ArrayPress\RegisterTables\Traits\StatusFormatters;
use ArrayPress\RegisterTables\Traits\TextFormatters;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * Class Columns
 *
 * Provides automatic column value formatting based on column names and value types.
 *
 * @since 1.0.0
 */
class Columns {

	use DateFormatters;
	use MoneyFormatters;
	use RelationFormatters;
	use StatusFormatters;
	use TextFormatters;

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
	 * The rule is the kit's, because it was answered here and again in the
	 * flyouts library and the two disagreed about zero: a price of 0.00 read
	 * as 0.00 in a list table and as an em-dash in the flyout that opened
	 * from it. This one was right, which is why it is the one that survived.
	 *
	 * Kept as a method of this class rather than replaced at forty call
	 * sites: it is public API, and the indirection costs nothing.
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 */
	public static function is_empty( $value ): bool {
		return Display::is_empty( $value );
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
		return Display::placeholder();
	}

/**
	 * Resolve the first available method on an object
	 *
	 * Iterates through method names and returns the result of the
	 * first one that exists and returns a non-empty value.
	 *
	 * @param object   $subject Object to check.
	 * @param string[] $methods Method names to try in order.
	 *
	 * @return string|null First non-empty result or null.
	 */
	private static function resolve_method( $subject, array $methods ): ?string {
		if ( ! is_object( $subject ) ) {
			return null;
		}

		foreach ( $methods as $method ) {
			if ( method_exists( $subject, $method ) ) {
				$result = $subject->$method();

				if ( ! empty( $result ) ) {
					return (string) $result;
				}
			}
		}

		return null;
	}
}
