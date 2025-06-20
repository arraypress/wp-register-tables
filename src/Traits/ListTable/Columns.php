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

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Columns
 *
 * Column rendering functionality for the Table_Instance class
 */
trait Columns {
	use ColumnFields;

	/**
	 * Get columns for the table
	 *
	 * Processes column configuration, respecting visibility settings, and
	 * prepares columns for display in the WP_List_Table.
	 *
	 * @return array Array of columns with titles
	 */
	public function get_columns(): array {
		$config_columns = $this->table_config['columns'] ?? [];
		$columns        = [];

		// Process columns to ensure proper format for WP_List_Table
		foreach ( $config_columns as $column_id => $column_config ) {
			// Skip columns based on visibility settings
			if ( isset( $column_config['visibility'] ) ) {
				$is_visible = $this->check_column_visibility( $column_config['visibility'] );
				if ( ! $is_visible ) {
					continue;
				}
			}

			// Process column format
			if ( is_array( $column_config ) ) {
				$columns[ $column_id ] = $column_config['title'] ?? ucfirst( $column_id );
			} else {
				$columns[ $column_id ] = $column_config;
			}
		}

		return apply_filters( "{$this->hook_prefix}columns", $columns, $this );
	}

	/**
	 * Check if a column should be visible
	 *
	 * @param mixed $visibility Role name, array of roles, or callback function
	 *
	 * @return bool Whether the column should be visible
	 */
	protected function check_column_visibility( $visibility ): bool {
		// If visibility is a callback, use its return value
		if ( is_callable( $visibility ) ) {
			return (bool) call_user_func( $visibility );
		}

		// If visibility is a string (single role) or array of roles
		if ( is_string( $visibility ) || is_array( $visibility ) ) {
			$roles        = (array) $visibility;
			$current_user = wp_get_current_user();

			// Check if user has any of the required roles
			foreach ( $roles as $role ) {
				if ( in_array( $role, $current_user->roles, true ) ) {
					return true;
				}
			}

			// No matching roles found
			return false;
		}

		// Default to visible if visibility setting is not recognized
		return true;
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
		if ( ( $column_name === 'name' || ! empty( $config['actions'] ) ) && ! empty( $this->table_config['actions'] ) ) {
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
	protected function render_column_value( $value, $item, $column_name, array $config ): string {
		$type = $config['type'] ?? 'text';

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

				default:
					// Get avatar if configured
					$avatar = ! empty( $config['with_avatar'] ) ? $this->render_avatar( $item, $config ) : '';

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

		// Add subrow content if configured
		if ( ! empty( $config['subrow'] ) ) {
			$subrow_content = $this->render_subrow_content( $item, $config['subrow'] );
			if ( ! empty( $subrow_content ) ) {
				$content .= $subrow_content;
			}
		}

		return $content;
	}

	/**
	 * Render subrow content for a column
	 *
	 * @param object|array $item          The item being displayed
	 * @param string|array $subrow_config The subrow configuration
	 *
	 * @return string The rendered subrow content
	 */
	protected function render_subrow_content( $item, $subrow_config ): string {
		// Handle simple callback format
		if ( is_string( $subrow_config ) ) {
			$callback = $subrow_config;
			$content  = $this->get_value_from_callback( $item, $callback );

			return ! empty( $content ) ? '<div class="column-subrow">' . $content . '</div>' : '';
		}

		// Handle array configuration format
		if ( is_array( $subrow_config ) ) {
			// Check condition if provided
			if ( isset( $subrow_config['condition'] ) && is_callable( $subrow_config['condition'] ) ) {
				if ( ! call_user_func( $subrow_config['condition'], $item ) ) {
					return ''; // Skip if condition returns false
				}
			}

			// Get content from callback
			if ( isset( $subrow_config['callback'] ) ) {
				$content = $this->get_value_from_callback( $item, $subrow_config['callback'] );

				// Skip if empty content
				if ( empty( $content ) && $content !== '0' ) {
					return '';
				}

				// Apply wrapper if specified
				if ( isset( $subrow_config['wrapper'] ) ) {
					return str_replace( '{content}', $content, $subrow_config['wrapper'] );
				}

				// Default wrapper
				return '<div class="column-subrow">' . $content . '</div>';
			}
		}

		return '';
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
	protected function make_linked_content( $content, $item, $link_config ): string {
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
	protected function parse_url_placeholders( $url, $item ): string {

		// Handle special placeholders first
		if ( $url === '{edit_url}' ) {
			$id = $this->get_item_id( $item );

			return get_table_url( $this->table_id, 'edit', $id );
		}

		if ( $url === '{view_url}' ) {
			$id = $this->get_item_id( $item );

			return get_table_url( $this->table_id, 'view', $id );
		}

		// Handle normal replacements
		$id         = $this->get_item_id( $item );
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
	protected function build_row_actions( $item ): string {
		if ( empty( $this->table_config['actions'] ) ) {
			return '';
		}

		$actions           = [];
		$item_id           = (string) $this->get_item_id( $item );
		$link_only_actions = [ 'add', 'edit', 'view' ];

		foreach ( $this->table_config['actions'] as $action_id => $action_config ) {
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
				$url    = get_table_url( $this->table_id, $action, $item_id );
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