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

use ArrayPress\Currencies\Currency;
use ArrayPress\RateFormat\Rate;
use ArrayPress\Stripe\Format;

/**
 * Amounts, rates and percentages.
 *
 * The currency is the site's unless the row says otherwise, because a table
 * of orders in mixed currencies that prints them all with one symbol is worse
 * than one that prints no symbol at all.
 *
 * Stripe amounts are separate on purpose: they arrive in the currency's
 * smallest unit, and dividing by a hundred is right for pounds and wrong for
 * yen. Guessing that from the number is not possible — it has to come from
 * the currency.
 */
trait MoneyFormatters {

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

		return sprintf( '<span class="price">%s</span>', esc_html( $formatted ) );
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

		return sprintf( '<span class="price">%s</span>', esc_html( $formatted ) );
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
		return Rate::render( $value, $item, $column_name ) ?? self::render_empty();
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
		return Rate::render_percentage( $value ) ?? self::render_empty();
	}
}
