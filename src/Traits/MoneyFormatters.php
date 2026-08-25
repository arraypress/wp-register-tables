<?php
/**
 * Money Column Formatters
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\Money\Money;
use ArrayPress\Money\Render;

/**
 * Amounts, rates and percentages.
 *
 * The currency is the site's unless the row says otherwise, because a table
 * of orders in mixed currencies that prints them all with one symbol is worse
 * than one that prints no symbol at all.
 *
 * Amounts arrive in the currency's smallest unit, and dividing by a hundred
 * is right for pounds and wrong for yen. Guessing it from the number is not
 * possible -- it has to come from the currency, which is what wp-money's
 * dataset is for.
 */
trait MoneyFormatters {

	/**
	 * Format a monetary amount with its currency symbol.
	 *
	 * Resolves the currency from the row -- get_currency() or
	 * get_currency_code() -- then the column config, then the site default.
	 *
	 * @param mixed  $value       Amount in the smallest currency unit.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports 'currency'.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public static function format_price( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		return Render::amount( (int) $value, self::resolve_currency( $item, $config ) );
	}

	/**
	 * Format a rate, which is a percentage or an amount depending on the row.
	 *
	 * A discount of `20` is twenty percent or twenty pounds, and the number
	 * cannot say which. The row carries the answer in a companion column --
	 * `rate_type` beside `rate` -- so that is what is read.
	 *
	 * @param mixed  $value       Rate value.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public static function format_rate( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		$type = $config['rate_type'] ?? self::resolve_rate_type( $item, $column_name );

		if ( self::is_percentage_type( $type ) ) {
			return self::format_percentage( $value, $item, $column_name, $config );
		}

		return Render::amount( (int) $value, self::resolve_currency( $item, $config ) );
	}

	/**
	 * Format a percentage.
	 *
	 * @param mixed  $value       Percentage value.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports 'decimals'.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public static function format_percentage( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		$decimals = (int) ( $config['decimals'] ?? 0 );

		return sprintf(
			'<span class="percentage">%s</span>',
			esc_html( number_format_i18n( (float) $value, $decimals ) . '%' )
		);
	}

	/**
	 * Which currency this row is in.
	 *
	 * @param object $item   Data object.
	 * @param array  $config Column configuration.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected static function resolve_currency( $item, array $config = [] ): string {
		$currency = $config['currency']
			?? self::resolve_method( $item, [ 'get_currency', 'get_currency_code' ] );

		if ( is_string( $currency ) && Money::supports( $currency ) ) {
			return $currency;
		}

		$default = (string) apply_filters( 'register_tables_default_currency', 'USD' );

		return Money::supports( $default ) ? $default : 'USD';
	}

	/**
	 * Whether a rate column holds a percentage rather than an amount.
	 *
	 * Read from the row's companion type column: `rate_type` beside `rate`,
	 * falling back to a plain `type`.
	 *
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 *
	 * @return string|null
	 *
	 * @since 1.0.0
	 */
	protected static function resolve_rate_type( $item, string $column_name = 'rate' ): ?string {
		$type = self::resolve_method( $item, [ 'get_' . $column_name . '_type', 'get_type' ] );

		if ( null !== $type ) {
			return is_string( $type ) ? $type : null;
		}

		// A plain object from $wpdb has properties rather than getters.
		// property_exists() throws a TypeError on an array, so the object
		// check has to come first.
		if ( is_object( $item ) ) {
			foreach ( [ $column_name . '_type', 'type' ] as $property ) {
				if ( property_exists( $item, $property ) && is_string( $item->$property ) ) {
					return $item->$property;
				}
			}
		}

		return null;
	}

	/**
	 * Whether a type names a percentage.
	 *
	 * @param string|null $type The type.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected static function is_percentage_type( ?string $type ): bool {
		return in_array( strtolower( (string) $type ), [ 'percent', 'percentage', '%' ], true );
	}
}
