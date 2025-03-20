<?php
/**
 * Table Instance Columns Trait
 *
 * Provides column rendering functionality for the Table_Instance class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\WP\Register\Traits;

// Exit if accessed directly
use Elementify\Create;

defined( 'ABSPATH' ) || exit;

/**
 * Columns
 *
 * Column rendering functionality for the Table_Instance class
 */
trait Columns {

	/**
	 * Get columns for the table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		$config_columns = $this->table_config['columns'] ?? [];
		$columns        = [];

		// Process columns to ensure proper format for WP_List_Table
		foreach ( $config_columns as $column_id => $column_config ) {
			if ( is_array( $column_config ) ) {
				$columns[ $column_id ] = $column_config['title'] ?? ucfirst( $column_id );
			} else {
				$columns[ $column_id ] = $column_config;
			}
		}

		return apply_filters( "{$this->hook_prefix}columns", $columns, $this );
	}

	/**
	 * Get sortable columns for the table
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		$sortable = [];

		if ( isset( $this->table_config['columns'] ) && is_array( $this->table_config['columns'] ) ) {
			foreach ( $this->table_config['columns'] as $column_id => $column_config ) {
				if ( is_array( $column_config ) && ! empty( $column_config['sortable'] ) ) {
					$sort_by                = $column_config['sort_by'] ?? $column_id;
					$is_default             = ! empty( $column_config['default_sort'] );
					$sortable[ $column_id ] = [ $sort_by, $is_default ];
				}
			}
		}

		return apply_filters( "{$this->hook_prefix}sortable_columns", $sortable, $this );
	}

	/**
	 * Default column renderer
	 *
	 * @param object|array $item        Item being displayed
	 * @param string       $column_name Column ID
	 *
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		// Allow short-circuiting column rendering
		$pre_content = apply_filters( "{$this->hook_prefix}pre_column_content", null, $item, $column_name, $this );
		if ( $pre_content !== null ) {
			return $pre_content;
		}

		// Get column configuration
		$config = isset( $this->table_config['columns'][ $column_name ] ) && is_array( $this->table_config['columns'][ $column_name ] )
			? $this->table_config['columns'][ $column_name ]
			: null;

		if ( ! $config ) {
			return $this->fallback_column_render( $item, $column_name );
		}

		// Get the raw value from source or fallback
		$source = $config['callback'] ?? $column_name;
		$value  = $this->get_value_from_callback( $item, $source );

		// Try fallback if value is empty
		if ( ( empty( $value ) && $value !== 0 ) && ! empty( $config['fallback'] ) ) {
			$value = $this->get_value_from_callback( $item, $config['fallback'] );
		}

		// Render the column based on its type
		$content = $this->render_column_value( $value, $item, $column_name, $config );

		// Apply link if needed
		if ( ! empty( $config['link'] ) ) {
			$content = $this->make_linked_content( $content, $item, $config['link'] );
		}

		// Add row actions if this is the primary column or actions explicitly requested
		if ( ( $column_name === 'name' || ! empty( $config['actions'] ) ) && ! empty( $this->table_config['row_actions'] ) ) {
			$content .= $this->build_row_actions( $item );
		}

		return apply_filters( "{$this->hook_prefix}column_content", $content, $item, $column_name, $this );
	}

	/**
	 * Fallback column renderer
	 *
	 * @param object|array $item        Item being displayed
	 * @param string       $column_name Column ID
	 *
	 * @return string
	 */
	protected function fallback_column_render( $item, string $column_name ): string {
		// Use custom row renderer if provided
		if ( isset( $this->table_config['callbacks']['row'] ) && is_callable( $this->table_config['callbacks']['row'] ) ) {
			return call_user_func( $this->table_config['callbacks']['row'], $item, $column_name, $this );
		}

		// Use column-specific method if available
		$method = 'column_' . $column_name;
		if ( method_exists( $this, $method ) ) {
			return $this->$method( $item );
		}

		// Simple fallback - just get and escape the value
		$value = $this->get_value_from_callback( $item, $column_name );

		return esc_html( $value );
	}

	/**
	 * Render a column value based on its type
	 *
	 * @param mixed        $value       The raw column value
	 * @param object|array $item        Item being displayed
	 * @param string       $column_name Column ID
	 * @param array        $config      Column configuration
	 *
	 * @return string
	 */
	protected function render_column_value( $value, $item, $column_name, $config ): string {
		$type    = $config['type'] ?? 'text';
		$content = '';

		// Handle interactive column types
		// Handle both interactive column types with one method
		if ( $type === 'toggle' || $type === 'featured' ) {
			return $this->render_interactive_column( $value, $config, $item, $column_name );
		}

		// Use callback if type is callable
		if ( ( is_array( $type ) || is_object( $type ) ) && is_callable( $type ) ) {
			$content = call_user_func( $type, $value, $item, $column_name, $this );
		} else {
			// Handle different column types
			switch ( $type ) {
				case 'date':
					$content = $this->render_date( $value, $config );
					break;

				case 'number':
					$content = $this->render_number( $value, $config );
					break;

				case 'money':
					$content = $this->render_money( $value, $config );
					break;

				case 'status':
					$content = $this->render_status( $value, $config );
					break;

				case 'boolean':
					$content = $this->render_boolean( $value, $config );
					break;

				case 'image':
					$content = $this->render_image( $value, $config );
					break;

				case 'email':
					$content = $this->render_email( $value, $config );
					break;

				case 'tel':
					$content = $this->render_telephone( $value, $config );
					break;

				case 'url':
					$content = $this->render_url( $value, $config );
					break;

				case 'map':
					$content = $this->render_map( $value, $config );
					break;

				case 'whatsapp':
					$content = $this->render_whatsapp( $value, $config );
					break;

				case 'telegram':
					$content = $this->render_telegram( $value, $config );
					break;

				case 'social':
					$content = $this->render_social( $value, $config );
					break;

				case 'user':
					$content = $this->render_user( $value, $config );
					break;

				case 'timeago':
					$content = $this->render_timeago( $value, $config );
					break;

				case 'color':
					$content = $this->render_color( $value, $config );
					break;

				case 'filesize':
					$content = $this->render_filesize( $value, $config );
					break;

				case 'taxonomy':
					$content = $this->render_taxonomy( $value, $config );
					break;

				case 'progress':
					$content = $this->render_progress( $value, $config );
					break;

				case 'rating':
					$content = $this->render_rating( $value, $config );
					break;

				case 'facetime':
					$content = $this->render_facetime( $value, $config );
					break;

				case 'anchor':
					$content = $this->render_anchor( $value, $config );
					break;

				case 'download':
					$content = $this->render_download( $value, $config );
					break;

				case 'sms':
					$content = $this->render_sms( $value, $config );
					break;

				case 'code':
					$content = $this->render_code( $value, $config );
					break;

				case 'clipboard':
					$content = $this->render_clipboard( $value, $config );
					break;

				case 'html':
					$content = empty( $value ) ? '' : wp_kses_post( $value );
					break;

				case 'text':
				default:
					// Handle text with optional avatar
					$avatar = '';
					if ( ! empty( $config['with_avatar'] ) && is_object( $item ) && method_exists( $item, 'get_avatar' ) ) {
						$avatar = $item->get_avatar();
					}

					if ( $value === '' || $value === null ) {
						$content = isset( $config['empty_value'] ) ? $avatar . esc_html( $config['empty_value'] ) : $avatar;
					} else {
						$content = $avatar . esc_html( $value );
					}
					break;
			}
		}

		// Apply class if specified
		if ( ! empty( $config['class'] ) ) {
			$classes = is_array( $config['class'] ) ? implode( ' ', $config['class'] ) : $config['class'];
			$content = '<span class="' . esc_attr( $classes ) . '">' . $content . '</span>';
		}

		return $content;
	}

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
	protected function render_interactive_column( $value, $config, $item, $column_name ) {
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
				$attributes,
				false,  // Not disabled
				true    // Include CSS from Elementify
			);

			return $element->render();
		} elseif ( $type === 'featured' ) {
			$element = Create::featured(
				$element_id,
				$is_active,
				$config['label'] ?? null,
				$attributes,
				false,  // Not disabled
				true    // Include CSS from Elementify
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
	protected function render_date( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		$date_format = $config['date_format'] ?? get_option( 'date_format' );
		$time_format = $config['time_format'] ?? get_option( 'time_format' );
		$show_time   = $config['show_time'] ?? true;

		// Handle different date formats
		$timestamp = null;

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
	protected function render_money( $value, $config ) {
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
	protected function render_number( $value, $config ) {
		if ( empty( $value ) && $value !== 0 ) {
			return '';
		}

		// Get options for the Number component
		$options = [
			'decimals'     => isset( $config['decimals'] ) ? intval( $config['decimals'] ) : 0,
			'prefix'       => $config['prefix'] ?? '',
			'suffix'       => $config['suffix'] ?? '',
			'show_sign'    => isset( $config['show_sign'] ) && $config['show_sign'],
			'colorize'     => isset( $config['colorize'] ) && $config['colorize'],
			'short_format' => isset( $config['short_format'] ) && $config['short_format'],
		];

		// Add tooltip if provided
		if ( isset( $config['tooltip'] ) ) {
			$options['tooltip'] = $config['tooltip'];
		}

		// Create Number component using Elementify
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
	protected function render_status( $value, $config ) {
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

		// Default attributes
		$attributes = $config['attributes'] ?? [];

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
	protected function render_boolean( $value, $config ) {
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
	 * Render image value
	 *
	 * @param mixed $value  The image URL or attachment ID
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_image( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		$size = $config['size'] ?? 32;
		$attr = [];

		// Add class if specified
		if ( isset( $config['class'] ) ) {
			$attr['class'] = $config['class'];
		}

		// Add alt text if specified
		if ( isset( $config['alt'] ) ) {
			$attr['alt'] = $config['alt'];
		}

		// Check if value is an attachment ID (numeric)
		if ( is_numeric( $value ) ) {
			return wp_get_attachment_image( (int) $value, [ $size, $size ], false, $attr );
		} else {
			// It's a URL, use HTML img tag with WordPress escaping
			if ( ! isset( $attr['alt'] ) ) {
				$attr['alt'] = ''; // Ensure alt is always present for accessibility
			}

			return sprintf(
				'<img src="%s" alt="%s" width="%d" height="%d" %s>',
				esc_url( $value ),
				esc_attr( $attr['alt'] ),
				(int) $size,
				(int) $size,
				isset( $attr['class'] ) ? 'class="' . esc_attr( $attr['class'] ) . '"' : ''
			);
		}
	}

	/**
	 * Render email link
	 *
	 * @param mixed $value  The email address
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_email( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get optional display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

		return Create::mailto( $value, $display_text, $attributes )->render();
	}

	/**
	 * Render telephone link
	 *
	 * @param mixed $value  The phone number
	 * @param array $config Column configuration
	 *
	 * @return string
	 */
	protected function render_telephone( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_url( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

		// Determine if external
		$is_external = ! isset( $config['external'] ) || (bool) $config['external'];

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
	protected function render_map( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_whatsapp( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get pre-filled message if set
		$message = $config['message'] ?? '';

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_telegram( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_facetime( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_anchor( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_download( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get filename
		$filename = $config['filename'] ?? '';

		// Get display text
		$display_text = $config['label'] ?? 'Download';

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_social( $value, $config ) {
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
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_sms( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get message
		$message = $config['message'] ?? '';

		// Get display text
		$display_text = $config['label'] ?? $value;

		// Get attributes
		$attributes = isset( $config['attributes'] ) && is_array( $config['attributes'] ) ? $config['attributes'] : [];

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
	protected function render_user( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get options for the User component
		$options = [
			'show_avatar' => isset( $config['show_avatar'] ) && (bool) $config['show_avatar'],
			'avatar_size' => isset( $config['avatar_size'] ) ? intval( $config['avatar_size'] ) : 24,
			'name_type'   => $config['name_type'] ?? 'display_name',
			'link'        => isset( $config['link'] ) && (bool) $config['link'],
			'show_role'   => isset( $config['show_role'] ) && (bool) $config['show_role'],
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
	protected function render_timeago( $value, $config ) {
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
	protected function render_color( $value, $config ) {
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
	protected function render_filesize( $value, $config ) {
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
	protected function render_taxonomy( $value, $config ) {
		// Early exit if empty
		if ( empty( $value ) ) {
			return '';
		}

		// Get taxonomy name
		$taxonomy = isset( $config['taxonomy'] ) ? $config['taxonomy'] : 'category';

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
	protected function render_progress( $value, $config ) {
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
	protected function render_rating( $value, $config ) {
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
	protected function render_code( $value, $config ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Get language for syntax highlighting
		$language = $config['language'] ?? '';

		// Get optional line numbers setting
		$line_numbers = isset( $config['line_numbers'] ) && (bool) $config['line_numbers'];

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
	protected function render_clipboard( $value, $config ) {
		if ( empty( $value ) && $value !== '0' ) {
			return '';
		}

		// Convert to string if not already
		$text = is_scalar( $value ) ? (string) $value : '';

		// Prepare options array
		$options = [
			'display_text' => $config['display_text'] ?? '',
			'max_length'   => $config['max_length'] ?? 0,
			'add_ellipsis' => isset( $config['add_ellipsis'] ) ? (bool) $config['add_ellipsis'] : true,
			'width'        => $config['width'] ?? '180px',
			'tooltip'      => $config['tooltip'] ?? 'Click to copy',
		];

		// Create the SimpleClipboard component
		return Create::clipboard( $text, $options )->render();
	}

	/**
	 * Get value from an object or array
	 *
	 * @param object|array          $item   Item being displayed
	 * @param string|array|callable $source Source name (property, method, or array key) or callback
	 *
	 * @return mixed Value from the source
	 */
	protected function get_value_from_callback( $item, $source ) {
		// Handle direct callback (function/closure)
		if ( is_callable( $source ) ) {
			return call_user_func( $source, $item, $this );
		}

		// Handle callback source in array format
		if ( is_array( $source ) && isset( $source['callback'] ) && is_callable( $source['callback'] ) ) {
			return call_user_func( $source['callback'], $item, $this );
		}

		// Handle object methods and properties (only if source is string)
		if ( is_object( $item ) && is_string( $source ) ) {
			// Direct method
			if ( method_exists( $item, $source ) ) {
				return $item->$source();
			}

			// Getter method
			$getter = 'get_' . $source;
			if ( method_exists( $item, $getter ) ) {
				return $item->$getter();
			}

			// Public property
			if ( property_exists( $item, $source ) ) {
				return $item->$source;
			}
		} // Handle array keys (only if source is string or int)
		elseif ( is_array( $item ) && ( is_string( $source ) || is_int( $source ) ) && isset( $item[ $source ] ) ) {
			return $item[ $source ];
		}

		return '';
	}

	/**
	 * Make linked content from base content
	 *
	 * @param string       $content     Base content to link
	 * @param object|array $item        Item being displayed
	 * @param string|array $link_config Link configuration
	 *
	 * @return string Content with link
	 */
	protected function make_linked_content( $content, $item, $link_config ) {
		if ( empty( $content ) ) {
			return '';
		}

		// Simple string URL
		if ( is_string( $link_config ) ) {
			$url = $this->parse_url_placeholders( $link_config, $item );

			return sprintf( '<a href="%s">%s</a>', esc_url( $url ), $content );
		} elseif ( is_array( $link_config ) && isset( $link_config['url'] ) ) {
			$url   = $this->parse_url_placeholders( $link_config['url'], $item );
			$attrs = '';

			// Add link attributes
			foreach ( [ 'target', 'title', 'class' ] as $attr ) {
				if ( ! empty( $link_config[ $attr ] ) ) {
					$attrs .= ' ' . $attr . '="' . esc_attr( $link_config[ $attr ] ) . '"';
				}
			}

			return sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $attrs, $content );
		}

		return $content;
	}

	/**
	 * Parse URL placeholders with item values
	 *
	 * @param string       $url  URL template with {placeholders}
	 * @param object|array $item Item to extract values from
	 *
	 * @return string Parsed URL
	 */
	protected function parse_url_placeholders( $url, $item ) {
		$tables = \ArrayPress\WP\Register\Tables::instance();

		// Handle special placeholders first
		if ( $url === '{edit_url}' ) {
			$id = $this->get_item_id( $item );
			return $tables->get_url($this->table_id, 'edit', $id);
		}

		if ( $url === '{view_url}' ) {
			$id = $this->get_item_id( $item );
			return $tables->get_url($this->table_id, 'view', $id);
		}

		// Handle normal replacements
		$id = $this->get_item_id( $item );
		$parsed_url = str_replace( '{id}', (string) $id, $url );

		// Replace other placeholders using regex
		$parsed_url = preg_replace_callback(
			'/\{([^}]+)\}/',
			function ( $matches ) use ( $item ) {
				$field = $matches[1];
				$value = $this->get_value_from_callback( $item, $field );
				return urlencode( (string) $value );
			},
			$parsed_url
		);

		// If not an absolute URL, assume it's an admin URL
		if ( ! preg_match( '/^https?:\/\//', $parsed_url ) ) {
			$parsed_url = admin_url( $parsed_url );
		}

		return $parsed_url;
	}

	/**
	 * Get item ID
	 *
	 * @param object|array $item The item
	 *
	 * @return int|string Item ID
	 */
	protected function get_item_id( $item ) {
		$id_field = $this->table_config['item_id_field'] ?? 'id';

		if ( is_object( $item ) ) {
			// Try get_id method first
			if ( method_exists( $item, 'get_id' ) ) {
				return $item->get_id();
			}

			// Then try property
			if ( property_exists( $item, $id_field ) ) {
				return $item->$id_field;
			}

			// Then try get_X method
			$getter = 'get_' . $id_field;
			if ( method_exists( $item, $getter ) ) {
				return $item->$getter();
			}
		} elseif ( is_array( $item ) && isset( $item[ $id_field ] ) ) {
			return $item[ $id_field ];
		}

		return 0;
	}

	/**
	 * Build row actions menu with improved URL handling
	 *
	 * @param object|array $item Item being displayed
	 *
	 * @return string Row actions HTML
	 */
	/**
	 * Build row actions menu with improved URL handling
	 *
	 * @param object|array $item Item being displayed
	 *
	 * @return string Row actions HTML
	 */
	protected function build_row_actions( $item ) {
		if ( empty( $this->table_config['row_actions'] ) ) {
			return '';
		}

		$actions           = [];
		$item_id           = (string) $this->get_item_id( $item );
		$link_only_actions = [ 'add', 'edit', 'view' ];
		$tables            = \ArrayPress\WP\Register\Tables::instance();

		foreach ( $this->table_config['row_actions'] as $action_id => $action_config ) {
			// Normalize config
			if ( $action_config === true ) {
				$action_config = [ 'action' => $action_id ];
			}

			if ( ! is_array( $action_config ) ) {
				continue;
			}

			// Check conditions
			if ( ! empty( $action_config['condition'] ) && is_callable( $action_config['condition'] ) ) {
				if ( ! call_user_func( $action_config['condition'], $item ) ) {
					continue;
				}
			}

			// Determine if this is a link-only action
			$is_link_only = in_array( $action_id, $link_only_actions ) ||
			                ( ! empty( $action_config['link_only'] ) && $action_config['link_only'] === true );

			// Build URL
			if ( ! empty( $action_config['url'] ) ) {
				$url = $this->parse_url_placeholders( $action_config['url'], $item );
			} else {
				// Use the action to generate the URL
				$action = $action_config['action'] ?? $action_id;
				$url = $tables->get_url( $this->table_id, $action, $item_id );
			}

			// Add nonce if needed
			if ( ! $is_link_only && ! empty( $action_config['nonce'] ) ) {
				$nonce_action = str_replace( '{id}', $item_id, $action_config['nonce'] );
				$url          = wp_nonce_url( $url, $nonce_action );
			}

			// Build attributes
			$attrs = '';

			if ( ! $is_link_only && ! empty( $action_config['confirm'] ) ) {
				$attrs .= ' onclick="return confirm(\'' . esc_js( $action_config['confirm'] ) . '\');"';
			}

			if ( ! empty( $action_config['class'] ) ) {
				$attrs .= ' class="' . esc_attr( $action_config['class'] ) . '"';
			}

			// Get title
			$title = $action_config['title'] ?? ucfirst( str_replace( '_', ' ', $action_id ) );

			$actions[ $action_id ] = sprintf(
				'<a href="%s"%s>%s</a>',
				esc_url( $url ),
				$attrs,
				esc_html( $title )
			);
		}

		return $this->row_actions( $actions );
	}

	/**
	 * Checkbox column renderer
	 *
	 * @param object|array $item Item being displayed
	 *
	 * @return string
	 */
	public function column_cb( $item ) {
		$id = $this->get_item_id( $item );
		if ( empty( $id ) ) {
			return '';
		}

		$singular   = $this->_args['singular'];
		$name_field = $this->table_config['item_name_field'] ?? 'name';
		$name       = $this->get_value_from_callback( $item, $name_field ) ?: sprintf( __( 'Item #%s', 'arraypress' ), $id );

		$checkbox = sprintf(
			'<input type="checkbox" name="%1$s[]" id="%1$s-%2$s" value="%2$s" />' .
			'<label for="%1$s-%2$s" class="screen-reader-text">%3$s</label>',
			esc_attr( $singular ),
			esc_attr( $id ),
			sprintf( __( 'Select %s', 'arraypress' ), esc_html( $name ) )
		);

		return apply_filters( "{$this->hook_prefix}column_cb", $checkbox, $item, $this );
	}

}