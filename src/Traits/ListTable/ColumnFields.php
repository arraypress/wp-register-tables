<?php
/**
 * Table Instance Column Fields Trait
 *
 * Provides column field rendering functionality for the Table_Instance class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use Elementify\Create;

/**
 * ColumnFields
 *
 * Column field type rendering functionality
 */
trait ColumnFields {

	/**
	 * Render an interactive column (toggle or featured)
	 *
	 * @param mixed        $value       The column value
	 * @param array        $config      Column configuration
	 * @param object|array $item        Item being displayed
	 * @param string       $column_name Column name
	 *
	 * @return string HTML output
	 */
	protected function render_interactive_column( $value, array $config, $item, string $column_name ): string {
		// Get the item ID
		$item_id = $this->get_item_id( $item );

		// Convert value to boolean
		$is_active = filter_var( $value, FILTER_VALIDATE_BOOLEAN );

		// Generate a nonce for security
		$nonce = wp_create_nonce( 'table_column_update_' . $this->table_id . '_' . $item_id );

		// Common data attributes for all interactive elements
		$attributes = [
			'data-item-id'     => $item_id,
			'data-column'      => $column_name,
			'data-table-id'    => $this->table_id,
			'data-nonce'       => $nonce,
			'data-interactive' => 'true'
		];

		// Add table-interactive class for JS targeting
		$class = 'table-interactive';
		if ( isset( $attributes['class'] ) ) {
			$attributes['class'] .= ' ' . $class;
		} else {
			$attributes['class'] = $class;
		}

		// Get column type
		$type = $config['type'] ?? 'toggle';

		// Generate unique element ID
		$element_id = "{$this->table_id}_{$column_name}_{$item_id}";

		// Render based on type
		if ( $type === 'toggle' ) {
			$element = Create::toggle(
				$element_id,
				$is_active,
				'1',
				$config['label'] ?? null,
				$attributes
			);

			return $element->render();
		} elseif ( $type === 'featured' ) {
			$element = Create::featured(
				$element_id,
				$is_active,
				$config['label'] ?? null,
				$attributes
			);

			return $element->render();
		}

		// Fallback for unknown types
		return esc_html( $value );
	}

	/**
	 * Render date value
	 *
	 * @param mixed $value  The date value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_date( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$date_format = $config['date_format'] ?? get_option( 'date_format' );
		$time_format = $config['time_format'] ?? get_option( 'time_format' );
		$show_time   = $config['show_time'] ?? true;

		// Handle different date formats
		if ( is_numeric( $value ) ) {
			$timestamp = (int) $value;
		} else {
			$timestamp = strtotime( $value );
		}

		if ( $timestamp ) {
			$formatted_date = date_i18n( $date_format, $timestamp );

			if ( $show_time ) {
				$formatted_time = date_i18n( $time_format, $timestamp );

				return sprintf(
					'<span title="%2$s">%1$s</span>',
					esc_html( $formatted_date ),
					esc_attr( "$formatted_date $formatted_time" )
				);
			}

			return esc_html( $formatted_date );
		}

		// If all else fails, return the original value
		return esc_html( $value );
	}

	/**
	 * Render money value
	 *
	 * @param mixed $value  The numeric value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_money( $value, array $config ): string {
		if ( empty( $value ) && $value !== 0 ) {
			return '';
		}

		$decimals  = $config['decimals'] ?? 2;
		$currency  = $config['currency'] ?? '$';
		$formatted = number_format_i18n( (float) $value, $decimals );

		$position = $config['currency_position'] ?? 'before';
		if ( $position === 'before' ) {
			return esc_html( $currency ) . esc_html( $formatted );
		} else {
			return esc_html( $formatted ) . esc_html( $currency );
		}
	}

	/**
	 * Render a numeric value
	 *
	 * @param mixed $value  The numeric value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_number( $value, array $config ): string {
		// Check if value is empty or zero with no override
		if ( empty( $value ) || ( ! $config['show_zero'] ) ) {
			return '&mdash;';
		}

		// Options for the Number component
		$options = [
			'decimals'     => isset( $config['decimals'] ) ? intval( $config['decimals'] ) : 0,
			'prefix'       => $config['prefix'] ?? '',
			'suffix'       => $config['suffix'] ?? '',
			'show_sign'    => isset( $config['show_sign'] ) && $config['show_sign'],
			'colorize'     => isset( $config['colorize'] ) && $config['colorize'],
			'short_format' => isset( $config['short_format'] ) && $config['short_format'],
		];

		return Create::number_format( $value, $options )->render();
	}

	/**
	 * Render status value
	 *
	 * @param mixed $value  The status value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_status( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$status  = (string) $value;
		$label   = ucfirst( $status );
		$options = [];

		// Get status options from config
		if ( ! empty( $config['options'] ) && isset( $config['options'][ $status ] ) ) {
			$option = $config['options'][ $status ];

			if ( is_array( $option ) ) {
				$label = $option['label'] ?? $label;

				// Map color to options if provided
				if ( isset( $option['color'] ) ) {
					$options['custom_color'] = $option['color'];
				}

				// Pass along any icon configuration
				if ( isset( $option['icon'] ) ) {
					$options['icon'] = $option['icon'];

					// If we have a custom icon type
					if ( isset( $option['dashicon'] ) && is_bool( $option['dashicon'] ) ) {
						$options['dashicon'] = $option['dashicon'];
					}
				}

				// Set icon position if specified
				if ( isset( $option['position'] ) ) {
					$options['position'] = $option['position'];
				}

				// Add any custom CSS classes
				if ( isset( $option['class'] ) ) {
					$attributes['class'] = $option['class'];
				}
			} else {
				$label = $option;
			}
		}

		// Get attributes
		$attributes = $this->get_attributes( $config );

		// Use Elementify's StatusBadge component
		return Create::badge( $label, $status, $options, $attributes )->render();
	}

	/**
	 * Render boolean value
	 *
	 * @param mixed $value  The boolean value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_boolean( $value, array $config ): string {
		// Create options array from config
		$options = [
			'true_value'  => $config['true_value'] ?? true,
			'false_value' => $config['false_value'] ?? false,
			'true_icon'   => $config['true_icon'] ?? 'yes-alt',
			'false_icon'  => $config['false_icon'] ?? 'no-alt',
			'true_label'  => $config['true_label'] ?? __( 'Yes', 'arraypress' ),
			'false_label' => $config['false_label'] ?? __( 'No', 'arraypress' )
		];

		// Use the BooleanIcon component
		return Create::boolean_icon( $value, $options )->render();
	}

	/**
	 * Render image value using the AttachmentImage component
	 *
	 * @param mixed $value  The image URL or attachment ID
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_image( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Prepare options for the AttachmentImage component
		$options = [
			'size'         => $config['size'] ?? 32,
			'alt'          => $config['alt'] ?? '',
			'lazy_load'    => $config['lazy_load'] ?? true,
			'rounded'      => $config['rounded'] ?? false,
			'circle'       => $config['circle'] ?? false,
			'fallback'     => $config['fallback'] ?? '',
			'use_fallback' => ! empty( $config['fallback'] ),
		];

		// Get attributes for the image
		$attributes = $this->get_attributes( $config );

//		var_dump( $value );

		// Use the AttachmentImage component
		return Create::attachment_image( $value, $options, $attributes )->render();
	}

	/**
	 * Render email link
	 *
	 * @param mixed $value  The email address
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_email( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get optional display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::mailto( $value, $display_text, $attributes )->render();
	}

	/**
	 * Render avatar content
	 *
	 * @param object $item   Item being displayed
	 * @param array  $config Avatar configuration
	 *
	 * @return string The avatar HTML
	 */
	protected function render_avatar( $item, array $config ): string {
		// Skip if item is not an object or doesn't have get_avatar method
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_avatar' ) ) {
			return '';
		}

		// Create args array with size if specified
		$args = [];
		if ( isset( $config['avatar_size'] ) ) {
			$args['size'] = $config['avatar_size'];
		}

		// Use avatar_callback if provided
		if ( ! empty( $config['avatar_callback'] ) && is_callable( $config['avatar_callback'] ) ) {
			return call_user_func( $config['avatar_callback'], $item, $args );
		}

		// Use get_avatar method from item, always passing an array
		return $item->get_avatar( $args );
	}

	/**
	 * Render telephone link
	 *
	 * @param mixed $value  The phone number
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_telephone( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::tel( $value, $display_text, $attributes )->render();
	}

	/**
	 * Render URL link
	 *
	 * @param mixed $value  The URL
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_url( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		// Determine if external
		$is_external = ! isset( $config['external'] ) || $config['external'];

		if ( $is_external ) {
			return Create::external_link( $value, $display_text, $attributes )->render();
		} else {
			return Create::a( $value, $display_text, $attributes )->render();
		}
	}

	/**
	 * Render map link
	 *
	 * @param mixed $value  The address
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_map( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::map( $value, $display_text, $attributes )->render();
	}

	/**
	 * Render WhatsApp link
	 *
	 * @param mixed $value  The phone number
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_whatsapp( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get pre-filled message if set
		$message = $config['message'] ?? '';

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::whatsapp( $value, $message, $display_text, $attributes )->render();
	}

	/**
	 * Render Telegram link
	 *
	 * @param mixed $value  The Telegram username
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_telegram( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::telegram( $value, $display_text, $attributes )->render();
	}

	/**
	 * Render FaceTime link
	 *
	 * @param mixed $value  The email or phone for FaceTime
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_facetime( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::facetime( $value, $display_text, $attributes )->render();
	}

	/**
	 * Render anchor link to page section
	 *
	 * @param mixed $value  The section ID (without #)
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_anchor( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::anchor( $value, $display_text, $attributes )->render();
	}

	/**
	 * Render download link
	 *
	 * @param mixed $value  The URL to the file
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_download( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get filename
		$filename = $config['filename'] ?? '';

		// Get display text
		$display_text = $config['label'] ?? 'Download';

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::download( $value, $filename, $display_text, $attributes )->render();
	}

	/**
	 * Render social media sharing link
	 *
	 * @param mixed $value  The URL to share or platform-specific value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_social( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get required platform
		if ( empty( $config['platform'] ) ) {
			return esc_html( $value ); // Fallback to plain text if no platform specified
		}

		$platform = $config['platform'];

		// Get URL to share (if applicable)
		$url = $config['url'] ?? $value;

		// Get display text
		$display_text = $config['label'] ?? 'Share on ' . ucfirst( $platform );

		// Get platform-specific params
		$params = isset( $config['params'] ) && is_array( $config['params'] ) ? $config['params'] : [];

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::social_share( $platform, $url, $params, $display_text, $attributes )->render();
	}

	/**
	 * Render SMS link
	 *
	 * @param mixed $value  The phone number
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_sms( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get message
		$message = $config['message'] ?? '';

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = $this->get_attributes( $config );

		return Create::sms( $value, $message, $display_text, $attributes )->render();
	}

	/**
	 * Render a user/author information
	 *
	 * @param mixed $value  The user ID
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_user( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get options for the User component
		$options = [
			'show_avatar' => isset( $config['show_avatar'] ) && $config['show_avatar'],
			'avatar_size' => isset( $config['avatar_size'] ) ? intval( $config['avatar_size'] ) : 24,
			'name_type'   => $config['name_type'] ?? 'display_name',
			'link'        => isset( $config['link'] ) && $config['link'],
			'show_role'   => isset( $config['show_role'] ) && $config['show_role'],
		];

		// Create User component using Elementify
		return Create::user( $value, $options )->render();
	}

	/**
	 * Render a relative time display
	 *
	 * @param mixed $value  The timestamp or date string
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_timeago( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get options for the TimeAgo component
		$options = [
			'show_tooltip'   => ! isset( $config['show_tooltip'] ) || $config['show_tooltip'],
			'tooltip_format' => $config['tooltip_format'] ?? '',
			'threshold'      => isset( $config['threshold'] ) ? (int) $config['threshold'] : 0,
			'cutoff'         => isset( $config['cutoff'] ) ? (int) $config['cutoff'] : 0,
		];

		// Create TimeAgo component using Elementify
		return Create::timeago( $value, $options )->render();
	}

	/**
	 * Render a color swatch
	 *
	 * @param mixed $value  The color value (hex, rgb, etc.)
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_color( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get options for the Color component
		$options = [
			'size'       => isset( $config['size'] ) ? intval( $config['size'] ) : 20,
			'show_value' => ! isset( $config['show_value'] ) || $config['show_value'],
			'tooltip'    => $config['tooltip'] ?? '',
			'shape'      => $config['shape'] ?? 'square',
		];

		// Create Color component using Elementify
		return Create::color_swatch( $value, $options )->render();
	}

	/**
	 * Render a filesize in human-readable format
	 *
	 * @param mixed $value  The filesize in bytes
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_filesize( $value, array $config ): string {
		if ( empty( $value ) && $value !== 0 ) {
			return '';
		}

		// Get options for the FileSize component
		$options = [
			'decimals'    => isset( $config['decimals'] ) ? intval( $config['decimals'] ) : 2,
			'binary'      => ! isset( $config['binary'] ) || $config['binary'],
			'tooltip_raw' => isset( $config['show_raw'] ) && $config['show_raw'],
		];

		// Create FileSize component using Elementify
		return Create::filesize( $value, $options )->render();
	}

	/**
	 * Render taxonomy terms
	 *
	 * @param mixed $value  The post ID or array of term IDs/slugs
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_taxonomy( $value, array $config ): string {
		// Early exit if empty
		if ( empty( $value ) ) {
			return '';
		}

		// Get taxonomy name
		$taxonomy = $config['taxonomy'] ?? 'category';

		// Get options for the Taxonomy component
		$options = [
			'link'       => ! isset( $config['link'] ) || $config['link'],
			'separator'  => $config['separator'] ?? ', ',
			'show_count' => isset( $config['show_count'] ) && $config['show_count'],
			'limit'      => isset( $config['limit'] ) ? intval( $config['limit'] ) : 0,
			'show_more'  => ! isset( $config['show_more'] ) || $config['show_more'],
			'badge'      => isset( $config['badge'] ) && $config['badge'],
		];

		// Add badge options if needed
		if ( $options['badge'] && isset( $config['badge_options'] ) && is_array( $config['badge_options'] ) ) {
			$options['badge_options'] = $config['badge_options'];
		}

		// Create Taxonomy component using Elementify
		return Create::taxonomy( $value, $taxonomy, $options )->render();
	}

	/**
	 * Render a progress bar
	 *
	 * @param mixed $value  The current value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_progress( $value, array $config ): string {
		if ( ( empty( $value ) && $value !== 0 ) || $value < 0 ) {
			return '';
		}

		// Get options for the ProgressBar component
		$options = [
			'show_percentage' => ! isset( $config['show_text'] ) || $config['show_text'],
			'size'            => $config['size'] ?? 'medium',
		];

		// Set total value
		$total = isset( $config['total'] ) ? floatval( $config['total'] ) : 100;

		// Add color settings if provided
		if ( ! empty( $config['color'] ) ) {
			$options['color'] = $config['color'];
		}

		// Create ProgressBar component using Elementify
		return Create::progress_bar( $value, $total, $options )->render();
	}

	/**
	 * Render a star rating
	 *
	 * @param mixed $value  The rating value
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_rating( $value, array $config ): string {
		if ( empty( $value ) && $value !== 0 ) {
			return '';
		}

		// Get options for the Rating component
		$options = [
			'max'        => isset( $config['max'] ) ? intval( $config['max'] ) : 5,
			'show_value' => ! isset( $config['show_value'] ) || $config['show_value'],
			'precision'  => isset( $config['precision'] ) ? intval( $config['precision'] ) : 1,
			'style'      => $config['style'] ?? 'stars',
			'dashicons'  => isset( $config['dashicon'] ) && $config['dashicon'],
		];

		// Add color settings if provided
		if ( ! empty( $config['filled_color'] ) ) {
			$options['filled_color'] = $config['filled_color'];
		}

		if ( ! empty( $config['empty_color'] ) ) {
			$options['empty_color'] = $config['empty_color'];
		}

		// Create Rating component using Elementify
		return Create::rating( (float) $value, $options )->render();
	}

	/**
	 * Render code content
	 *
	 * @param mixed $value  The code content
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_code( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Get language for syntax highlighting
		$language = $config['language'] ?? '';

		// Get optional line numbers setting
		$line_numbers = isset( $config['line_numbers'] ) && $config['line_numbers'];

		// Get optional max height
		$max_height = $config['max_height'] ?? '';

		// Create options array
		$options = [
			'language'     => $language,
			'line_numbers' => $line_numbers,
		];

		// Add max height if specified
		if ( ! empty( $max_height ) ) {
			$options['max_height'] = $max_height;
		}

		// Additional options that might be useful
		if ( isset( $config['highlight_lines'] ) ) {
			$options['highlight_lines'] = $config['highlight_lines'];
		}

		if ( isset( $config['title'] ) ) {
			$options['title'] = $config['title'];
		}

		// Create Code component using Elementify
		return Create::code( $value, $options )->render();
	}

	/**
	 * Render clipboard text with copy button
	 *
	 * @param mixed $value  The text to display and copy
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_clipboard( $value, array $config ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Convert to string if not already
		$text = is_scalar( $value ) ? (string) $value : '';

		// Prepare options array
		$options = [
			'display_text' => $config['display_text'] ?? '',
			'max_length'   => $config['max_length'] ?? 0,
			'add_ellipsis' => ! isset( $config['add_ellipsis'] ) || $config['add_ellipsis'],
			'width'        => $config['width'] ?? '180px',
			'tooltip'      => $config['tooltip'] ?? __( 'Click to copy', 'arraypress' )
		];

		// Create the SimpleClipboard component
		return Create::clipboard( $text, $options )->render();
	}

	/**
	 * Get attributes array from config
	 *
	 * @param array $config   Configuration array
	 * @param array $defaults Default attributes to merge with config attributes
	 *
	 * @return array Processed attributes array
	 */
	protected function get_attributes( array $config, array $defaults = [] ): array {
		$attributes = $defaults;

		if ( isset( $config['attributes'] ) && is_array( $config['attributes'] ) ) {
			$attributes = array_merge( $attributes, $config['attributes'] );
		}

		return $attributes;
	}

}