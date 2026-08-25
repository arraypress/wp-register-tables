<?php
/**
 * Date Column Formatters
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\Dates\Site;
use ArrayPress\FormatUtils\Duration;

/**
 * When something happened, and how long it took.
 *
 * In the site's own date format rather than a hardcoded one: a column that
 * prints American dates on a site configured for British ones is wrong twelve
 * days a month and plausible the rest of the time.
 */
trait DateFormatters {

	/**
	 * Format a date/datetime value as a human-readable time difference
	 *
	 * Renders as relative time -- "2 hours ago" -- with the exact moment, in
	 * the site's timezone and format, on hover.
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
		$pair = Site::relative_with_exact( (string) $value );

		if ( '' === $pair['text'] ) {
			return self::render_empty();
		}

		return sprintf(
			'<time datetime="%s" title="%s">%s</time>',
			esc_attr( (string) $value ),
			esc_attr( $pair['title'] ),
			esc_html( $pair['text'] )
		);
	}

	/**
	 * Format a duration value in seconds as a human-readable string
	 *
	 * Renders a count of seconds as "2h 15m".
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
		if ( ! is_numeric( $value ) ) {
			return self::render_empty();
		}

		return esc_html( Duration::compact( (int) $value ) );
	}
}
