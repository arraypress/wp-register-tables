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

namespace ArrayPress\RegisterTables;

use ArrayPress\Countries\Countries;
use ArrayPress\Currencies\Currency;
use ArrayPress\DateUtils\Dates;
use ArrayPress\RateFormat\Rate;
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
		'image'      => [
			'exact'    => [ 'image', 'avatar', 'thumbnail', 'logo', 'photo', 'icon' ],
			'contains' => [ '_image', '_avatar', '_thumbnail', '_logo', '_photo' ],
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
	 * @param string $column_name  Column name.
	 * @param mixed  $value        Column value.
	 * @param object $item         Data object.
	 * @param array  $config       Column config from registration. Supports:
	 *                             - 'styles'   (array)        Status => badge type mappings.
	 *                             - 'size'     (string|array) Image size name or [w, h] array.
	 *                             - 'decimals' (int)          Decimal places for file_size.
	 *
	 * @return string Formatted HTML.
	 */
	public static function auto_format(
		string $column_name,
		$value,
		$item,
		array $config = []
	): string {
		// Handle empty values
		if ( self::is_empty( $value ) ) {
			return self::render_empty();
		}

		$type = self::detect_type( $column_name );

		return match ( $type ) {
			'email' => self::format_email( $value ),
			'country' => Countries::render( $value ) ?? self::render_empty(),
			'date' => Dates::render_date( $value ) ?? self::render_empty(),
			'duration' => Dates::render_duration( $value ) ?? self::render_empty(),
			'price' => Currency::render( $value, $item ) ?? self::render_empty(),
			'rate' => Rate::render( $value, $item, $column_name ) ?? self::render_empty(),
			'percentage' => Rate::render_percentage( $value ) ?? self::render_empty(),
			'status' => self::format_status( $value, $config['styles'] ?? [] ),
			'count' => self::format_count( $value ),
			'image' => self::format_image( $value, $config['size'] ?? null ),
			'url' => self::format_url( $value ),
			'boolean' => self::format_boolean( $value, $column_name, $config['styles'] ?? [] ),
			'code' => self::format_code( $value ),
			'file_size' => self::format_file_size( $value, $config['decimals'] ?? null ),
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

	/**
	 * Format a status value as a badge
	 *
	 * @param string $value  Status string.
	 * @param array  $styles Custom status => badge type mappings.
	 *
	 * @return string Badge HTML.
	 */
	public static function format_status( string $value, array $styles = [] ): string {
		return self::get_badge( $styles )->render( $value );
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
	 * @param string $url URL value.
	 *
	 * @return string Formatted URL HTML.
	 */
	public static function format_url( string $url ): string {
		$display_url = wp_parse_url( $url, PHP_URL_HOST ) ?: $url;

		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $url ),
			esc_html( $display_url )
		);
	}

	/**
	 * Format image value as a thumbnail
	 *
	 * Accepts either a WordPress attachment ID or a raw URL. Attachment IDs
	 * use wp_get_attachment_image() for proper srcset and responsive handling.
	 *
	 * @param mixed        $value Attachment ID (int) or image URL (string).
	 * @param string|array $size  WordPress image size name or [width, height] array.
	 *
	 * @return string Formatted image HTML.
	 */
	public static function format_image( $value, $size = null ): string {
		$size = $size ?? 'thumbnail';

		if ( is_numeric( $value ) ) {
			$image = wp_get_attachment_image( (int) $value, $size, false, [
				'class'   => 'column-thumbnail',
				'loading' => 'lazy',
			] );

			if ( $image ) {
				$full_url = wp_get_attachment_url( (int) $value );

				return $full_url
					? sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $full_url ), $image )
					: $image;
			}

			return self::render_empty();
		}

		// Raw URL fallback
		return sprintf(
			'<img src="%s" class="column-thumbnail" alt="" loading="lazy" />',
			esc_url( (string) $value )
		);
	}

	/**
	 * Format boolean value
	 *
	 * @param mixed  $value       Boolean value.
	 * @param string $column_name Column name (for special handling).
	 * @param array  $styles      Custom status => badge type mappings.
	 *
	 * @return string Formatted boolean HTML.
	 */
	public static function format_boolean( $value, string $column_name = '', array $styles = [] ): string {
		$is_true = filter_var( $value, FILTER_VALIDATE_BOOLEAN );

		// Special handling for test/live mode
		if ( $column_name === 'is_test' || $column_name === 'test_mode' ) {
			$badge = self::get_badge( $styles );

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
	 * Format file size value
	 *
	 * Converts bytes into human-readable file size format using WordPress's size_format().
	 *
	 * @param mixed    $value    Size in bytes.
	 * @param null|int $decimals Number of decimal places (default 1).
	 *
	 * @return string Formatted file size HTML.
	 */
	public static function format_file_size( $value, ?int $decimals = null ): string {
		$decimals = $decimals ?? 1;

		if ( ! is_numeric( $value ) || $value < 0 ) {
			return self::render_empty();
		}

		$formatted = size_format( (int) $value, $decimals );

		return sprintf( '<span class="file-size">%s</span>', esc_html( $formatted ) );
	}

}