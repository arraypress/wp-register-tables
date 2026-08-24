<?php
/**
 * Text Column Formatters
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\StatusBadge\StatusBadge;
use ArrayPress\Stripe\Format;

/**
 * Values that are read rather than counted: an address, a number to ring, a
 * link, a snippet of code.
 *
 * Mostly a matter of deciding what to wrap it in. An email is a mailto link,
 * a URL is shortened to its host so a column of them stays a column, and code
 * gets a monospace face because a hash that wraps mid-string is unreadable.
 *
 * All of it escapes. These take a value straight out of a database row and a
 * list table prints hundreds of them.
 */
trait TextFormatters {

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

		return sprintf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
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

		return sprintf(
			'<a href="tel:%s">%s</a>',
			esc_attr( $tel ),
			esc_html( $phone )
		);
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

		return sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $url ),
			esc_html( $display_url )
		);
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

		return $output;
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

		return $output;
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

		return sprintf( '<span class="file-size">%s</span>', esc_html( $formatted ) );
	}
}
