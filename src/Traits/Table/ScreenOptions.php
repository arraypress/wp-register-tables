<?php
/**
 * Table Render Trait
 *
 * Provides rendering functionality for the Tables class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\Table;

// Exit if accessed directly
use ArrayPress\CustomTables\ListTable;
use WP_Screen;

defined( 'ABSPATH' ) || exit;

trait ScreenOptions {

	/**
	 * Setup screen options for per page items and column visibility
	 *
	 * @param WP_Screen $screen       Current screen
	 * @param string    $table_id     Table ID
	 * @param array     $table_config Table configuration
	 */
	private function setup_screen_options( WP_Screen $screen, string $table_id, array $table_config ): void {
		if ( empty( $table_config['screen_options'] ) || empty( $table_config['screen_options']['enabled'] ) ) {
			return;
		}

		// Add column visibility controls to screen options
		if ( ! empty( $table_config['screen_options']['columns'] ) ) {
			add_filter( 'screen_settings', function ( $settings ) use ( $screen ) {
				return $this->add_columns_screen_options( $settings, $screen );
			} );

			// Hook into the screen options update action
			add_action( 'wp_ajax_hidden-columns', array( $this, 'ajax_hidden_columns' ), 5 );
		}

		// Per page option
		$per_page = $table_config['screen_options']['per_page'] ?? [];
		if ( ! empty( $per_page ) ) {
			$label   = $per_page['label'] ?? sprintf( __( '%s per page', 'arraypress' ), $table_config['plural'] );
			$default = $per_page['default'] ?? $table_config['per_page'] ?? 30;
			$option  = $per_page['option'] ?? 'table_' . $table_id . '_per_page';

			add_screen_option( 'per_page', [
				'label'   => $label,
				'default' => $default,
				'option'  => $option
			] );
		}
	}

	/**
	 * Handle AJAX request to update hidden columns
	 */
	public function ajax_hidden_columns() {
		// Check nonce
		check_ajax_referer( 'screen-options-nonce', 'screenoptionnonce' );

		if ( empty( $_POST['screen'] ) ) {
			wp_die( 0 );
		}

		$screen_id = sanitize_text_field( $_POST['screen'] );
		$screen    = convert_to_screen( $screen_id );
		$table_id  = $this->get_table_id_for_screen( $screen );

		if ( ! $table_id ) {
			return; // Not our screen, let WordPress handle it
		}

		// Get the columns from the request
		$hidden = array();
		if ( ! empty( $_POST['hidden'] ) ) {
			$hidden_raw = sanitize_text_field( $_POST['hidden'] );
			$hidden     = explode( ',', $hidden_raw );
			$hidden     = array_map( 'sanitize_key', $hidden );
			$hidden     = array_filter( $hidden );
		}

		// Save the user preference
		update_user_option( get_current_user_id(), 'manage' . $screen->id . 'columnshidden', $hidden );

		wp_die( 1 );
	}

	/**
	 * Add column visibility controls to screen options
	 *
	 * @param string    $screen_settings Current screen settings HTML
	 * @param WP_Screen $screen          Current screen
	 *
	 * @return string Updated screen settings HTML
	 */
	private function add_columns_screen_options( string $screen_settings, WP_Screen $screen ): string {
		// Get the table ID for this screen
		$table_id = $this->get_table_id_for_screen( $screen );

		if ( ! $table_id || ! isset( $this->registered_tables[ $table_id ] ) ) {
			return $screen_settings;
		}

		$table_config = $this->registered_tables[ $table_id ];

		// Skip if screen options are disabled or columns option is not enabled
		if ( empty( $table_config['screen_options'] ) ||
		     empty( $table_config['screen_options']['enabled'] ) ||
		     empty( $table_config['screen_options']['columns'] ) ) {
			return $screen_settings;
		}

		// Create a temporary table instance to get columns
		$table   = new ListTable( $table_id, $table_config );
		$columns = $table->get_columns();

		// Remove checkbox column if present
		if ( isset( $columns['cb'] ) ) {
			unset( $columns['cb'] );
		}

		// Get currently hidden columns
		$hidden = get_user_option( 'manage' . $screen->id . 'columnshidden' );
		if ( ! is_array( $hidden ) ) {
			$hidden = array();
		}

		// Identify primary column (usually the first column after checkbox)
		$primary_column = '';
		if ( ! empty( $table_config['primary_column'] ) ) {
			// If explicitly defined in config
			$primary_column = $table_config['primary_column'];
		} else {
			// Otherwise, assume it's the first column after checkbox
			$keys = array_keys( $columns );
			if ( ! empty( $keys ) ) {
				$primary_column = $keys[0];
			}
		}

		// Start building the column visibility controls
		$screen_settings .= '<fieldset class="metabox-prefs">';
		$screen_settings .= '<legend>' . __( 'Columns', 'arraypress' ) . '</legend>';

		foreach ( $columns as $column_name => $column_display_name ) {
			// Skip checkbox column (already filtered out) and primary column
			if ( $column_name === 'cb' || $column_name === $primary_column ) {
				continue;
			}

			$checked         = ! in_array( $column_name, $hidden );
			$id              = "{$column_name}-hide";
			$screen_settings .= sprintf(
				'<label><input class="hide-column-tog" name="%1$s" type="checkbox" id="%1$s" value="%2$s" %3$s />%4$s</label>',
				esc_attr( $id ),
				esc_attr( $column_name ),
				checked( $checked, true, false ),
				esc_html( $column_display_name )
			);
		}

		$screen_settings .= '</fieldset>';

		return $screen_settings;
	}

	/**
	 * Get table ID for current screen.
	 *
	 * @param WP_Screen $screen Current screen
	 *
	 * @return string|null Table ID or null if not found
	 */
	public function get_table_id_for_screen( WP_Screen $screen ): ?string {
		if ( ! $screen ) {
			return null;
		}

		// Direct match from screen map
		if ( isset( $this->screen_map[ $screen->id ] ) ) {
			return $this->screen_map[ $screen->id ];
		}

		// Try to extract from page parameter
		if ( ! empty( $_GET['page'] ) ) {
			$page = sanitize_key( $_GET['page'] );

			foreach ( $this->registered_tables as $id => $config ) {
				if ( $config['slug'] === $page ) {
					return $id;
				}
			}
		}

		return null;
	}

}