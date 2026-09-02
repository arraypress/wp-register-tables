<?php
/**
 * Status Column Formatters
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\Countries\Countries;
use ArrayPress\StatusBadge\StatusBadge;

/**
 * Columns that are a state, a tally or a swatch.
 *
 * A status renders as a badge with a class derived from its own value, which
 * is why it goes through sanitize_html_class first — a status is whatever the
 * row happens to hold, and it lands in a class attribute.
 *
 * A count of nought is a count, not an absence: printing a dash there says
 * the column failed to load rather than that the answer is none.
 */
trait StatusFormatters {

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

		return self::get_badge( $styles )->render( (string) $value );
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

		return $output;
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
		return Countries::render( (string) $value ) ?? self::render_empty();
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
		$color = trim( (string) $value );

		if ( '' === $color ) {
			return self::render_empty();
		}

		// The swatch only for a value that is a colour. It lands in a style
		// attribute, and a style attribute is a place a stored string can do
		// more than colour a box -- so anything that is not a hex or an rgb()
		// triple is printed as text and not painted.
		$swatch = self::is_css_color( $color )
			? sprintf( '<span class="column-color-swatch" style="background-color:%s;"></span>', esc_attr( $color ) )
			: '';

		return sprintf(
			'<span class="column-color">%s<code class="code">%s</code></span>',
			$swatch,
			esc_html( $color )
		);
	}

	/**
	 * Whether a string is a colour a swatch can safely be painted with.
	 *
	 * Hex through core's own check, and rgb() or rgba() by shape. Named
	 * colours are not accepted: the list is long, and the cost of leaving
	 * one out is a missing swatch rather than a missing row.
	 *
	 * @param string $color The candidate.
	 *
	 * @return bool
	 */
	private static function is_css_color( string $color ): bool {
		if ( sanitize_hex_color( $color ) ) {
			return true;
		}

		return (bool) preg_match(
			'/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/i',
			$color
		);
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

		return $output;
	}
}
