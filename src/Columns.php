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
	 * @param string $column_name  Column name.
	 * @param mixed  $value        Column value.
	 * @param object $item         Data object.
	 * @param array  $config       Column config from registration. Supports:
	 *                             - 'type'     (string|false) Explicit column type or false to disable.
	 *                             - 'styles'   (array)        Status => badge type mappings.
	 *                             - 'size'     (string|array) Image size name or [w, h] array.
	 *                             - 'decimals' (int)          Decimal places for file_size.
	 *                             - 'singular' (string)       Singular label for items type.
	 *                             - 'plural'   (string)       Plural label for items type.
	 *                             - 'taxonomy' (string)       Taxonomy slug for term links.
	 *                             - 'avatar'   (int)          Avatar size in pixels for user type.
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
			'email' => self::format_email( $value ),
			'phone' => self::format_phone( $value ),
			'country' => Countries::render( $value ) ?? self::render_empty(),
			'date' => Dates::render_date( $value ) ?? self::render_empty(),
			'duration' => Dates::render_duration( $value ) ?? self::render_empty(),
			'price' => self::format_price( $value, $item ),
			'rate' => Rate::render( $value, $item, $column_name ) ?? self::render_empty(),
			'percentage' => Rate::render_percentage( $value ) ?? self::render_empty(),
			'status' => self::format_status( $value, $config['styles'] ?? [] ),
			'count' => self::format_count( $value ),
			'items' => self::format_items( $value, $config['singular'] ?? null, $config['plural'] ?? null ),
			'user' => self::format_user( $value, $config['avatar'] ?? null ),
			'customer' => self::format_customer( $value, $item, $config ),
			'taxonomy' => self::format_taxonomy( $value, $config['taxonomy'] ?? null ),
			'image' => self::format_image( $value, $config['size'] ?? null ),
			'color' => self::format_color( $value ),
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
	 * Format phone number value
	 *
	 * Renders a clickable tel: link. Strips non-numeric characters (except leading +)
	 * for the href while preserving the original format for display.
	 *
	 * @param string $phone Phone number.
	 *
	 * @return string Phone link HTML.
	 */
	public static function format_phone( string $phone ): string {
		$tel = preg_replace( '/[^\d+]/', '', $phone );

		return sprintf(
			'<a href="tel:%s">%s</a>',
			esc_attr( $tel ),
			esc_html( $phone )
		);
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
	 * Format items summary
	 *
	 * Accepts an array of item names or a numeric count. Arrays render as:
	 * - 1 item:  "Item Name"
	 * - 2 items: "Item Name and 1 other item"
	 * - 3+ items: "Item Name and 2 other items"
	 *
	 * Numeric values render as "4 items".
	 *
	 * @param mixed       $value    Array of item names/objects or numeric count.
	 * @param string|null $singular Singular label (default: "item").
	 * @param string|null $plural   Plural label (default: "items").
	 *
	 * @return string Formatted items HTML.
	 */
	public static function format_items( $value, ?string $singular = null, ?string $plural = null ): string {
		$singular = $singular ?? __( 'item', 'arraypress' );
		$plural   = $plural ?? __( 'items', 'arraypress' );

		// Numeric count
		if ( is_numeric( $value ) ) {
			$count = (int) $value;

			if ( $count === 0 ) {
				return self::render_empty();
			}

			return sprintf(
				'<span class="column-items">%s %s</span>',
				esc_html( number_format_i18n( $count ) ),
				esc_html( $count === 1 ? $singular : $plural )
			);
		}

		// Array of items
		if ( ! is_array( $value ) ) {
			return self::render_empty();
		}

		// Normalize: extract name from objects if needed
		$names = array_map( function ( $item ) {
			if ( is_object( $item ) && isset( $item->name ) ) {
				return $item->name;
			}

			return (string) $item;
		}, $value );

		$names = array_filter( $names );
		$count = count( $names );

		if ( $count === 0 ) {
			return self::render_empty();
		}

		$first = esc_html( $names[0] );
		$rest  = $count - 1;

		if ( $rest === 0 ) {
			return sprintf( '<span class="column-items">%s</span>', $first );
		}

		return sprintf(
			'<span class="column-items">%s <span class="column-items-rest">%s %s %s</span></span>',
			$first,
			esc_html__( 'and', 'arraypress' ),
			esc_html( number_format_i18n( $rest ) ),
			esc_html( sprintf(
			/* translators: %s: singular or plural item label */
				_n( 'other %s', 'other %s', $rest, 'arraypress' ),
				$rest === 1 ? $singular : $plural
			) )
		);
	}

	/**
	 * Format user/author value
	 *
	 * Accepts a user ID and renders an avatar with display name linked to the
	 * user's profile edit screen.
	 *
	 * @param mixed    $value  User ID.
	 * @param int|null $avatar Avatar size in pixels (default: 32).
	 *
	 * @return string Formatted user HTML.
	 */
	public static function format_user( $value, ?int $avatar = null ): string {
		$avatar_size = $avatar ?? 32;
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

		return sprintf(
			'<span class="column-user">%s %s</span>',
			$avatar_img,
			$name_html
		);
	}

	/**
	 * Format a customer/person value.
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
	 * @param mixed  $value   Object, user ID, or customer ID.
	 * @param object $item    The row data object.
	 * @param array  $config  Column configuration. Supports:
	 *                        - 'avatar'       (int)    Avatar size in pixels (default: 32).
	 *                        - '_filter_url'  (string) Explicit URL override for name link.
	 *
	 * @return string Formatted customer HTML.
	 */
	public static function format_customer( $value, $item, array $config = [] ): string {
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
				return self::format_user( $value, $avatar_size );
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

		return sprintf(
			'<span class="column-customer">%s<span class="column-customer-detail">%s%s</span></span>',
			$avatar_html,
			$name_html,
			$email_html
		);
	}

	/**
	 * Resolve the first available method on an object.
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
	 * Format taxonomy terms
	 *
	 * Accepts an array of term names, term objects, or WP_Term objects.
	 * When a taxonomy slug is provided, term names link to their admin edit screens.
	 *
	 * @param mixed       $value    Array of term names, term objects, or WP_Term objects.
	 * @param string|null $taxonomy Taxonomy slug for generating admin links.
	 *
	 * @return string Formatted terms HTML.
	 */
	public static function format_taxonomy( $value, ?string $taxonomy = null ): string {
		if ( ! is_array( $value ) ) {
			return self::render_empty();
		}

		$terms = [];

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

		return sprintf( '<span class="column-taxonomy">%s</span>', implode( ', ', $terms ) );
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
	 * @param mixed             $value Attachment ID (int) or image URL (string).
	 * @param string|array|null $size  WordPress image size name or [width, height] array.
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
	 * Format color value
	 *
	 * Renders a small color swatch alongside the hex/rgb value.
	 *
	 * @param string $value Color value (hex, rgb, or any valid CSS color).
	 *
	 * @return string Formatted color HTML.
	 */
	public static function format_color( string $value ): string {
		if ( empty( $value ) ) {
			return self::render_empty();
		}

		return sprintf(
			'<span class="column-color"><span class="column-color-swatch" style="background-color:%s;"></span><code class="code">%s</code></span>',
			esc_attr( $value ),
			esc_html( $value )
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
			? '<span class="dashicons dashicons-yes-alt column-boolean-yes"></span><span class="screen-reader-text">' . esc_html__( 'Yes', 'arraypress' ) . '</span>'
			: '<span class="dashicons dashicons-minus column-boolean-no"></span><span class="screen-reader-text">' . esc_html__( 'No', 'arraypress' ) . '</span>';
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
	 * @param int|null $decimals Number of decimal places.
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

	/**
	 * Format a price/currency value without recurring interval
	 *
	 * Renders a monetary amount using the item's currency. Does not
	 * auto-resolve recurring intervals since the price type matches
	 * many column names (revenue, total_spent, balance, cost) where
	 * interval display would be incorrect. For columns that need
	 * interval display, use a column callback with Currency::render().
	 *
	 * @param mixed  $value Amount in smallest currency unit.
	 * @param object $item  Data object (checked for currency).
	 *
	 * @return string Formatted price HTML.
	 */
	public static function format_price( $value, $item ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		$currency  = Currency::resolve( $item );
		$formatted = Currency::format( intval( $value ), $currency );

		return sprintf( '<span class="price">%s</span>', esc_html( $formatted ) );
	}

}