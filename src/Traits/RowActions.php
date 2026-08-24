<?php
/**
 * Row Actions
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Manager;

/**
 * The links under a row: edit, delete, and whatever else a table declared.
 *
 * Each is built with the capability it demands and a nonce named for the
 * action and the row — a row action is a link, so without both it is a URL
 * anyone can be talked into visiting.
 *
 * Delete is added automatically when a table has a delete callback and has
 * not declared one itself, because a table that can delete and shows no way
 * to is a table with a missing feature rather than a deliberate one.
 */
trait RowActions {

/**
     * Handle row actions for primary column
     *
     * Builds and renders row actions (Edit, View, Delete, etc.) that appear
     * on hover below the primary column content.
     *
     * @param object $item        Data object.
     * @param string $column_name Current column being rendered.
     * @param string $primary     Primary column identifier.
     *
     * @return string Row actions HTML (empty for non-primary columns).
     * @since 1.0.0
     *
     */
    protected function handle_row_actions( $item, $column_name, $primary ) {
        // Only show actions on primary column
        if ( $column_name !== $primary ) {
            return '';
        }

        $item_id = $this->get_item_id( $item );
        $actions = [];

        // Build actions from config or callback
        if ( is_callable( $this->config['row_actions'] ) ) {
            $actions = call_user_func( $this->config['row_actions'], $item, $item_id );
        } else {
            $actions = $this->build_row_actions( $item, $item_id );
        }

        // Auto-add delete action if delete callback exists and no explicit delete action
        if ( $this->should_add_auto_delete_action( $actions ) ) {
            $actions['delete'] = $this->build_delete_action( $item_id );
        }

        /**
         * Filter row actions for a specific table
         *
         * @param array  $actions Row action links.
         * @param object $item    Data object.
         *
         * @since 1.0.0
         *
         */
        $actions = apply_filters( "arraypress_table_row_actions_{$this->id}", $actions, $item );

        return $this->row_actions( $actions );
    }

/**
     * Build row actions from configuration
     *
     * Processes the row_actions config array to generate action links.
     * Supports conditions, capabilities, and various action types.
     *
     * @param object $item    Data object.
     * @param int    $item_id Item ID.
     *
     * @return array Associative array of action key => HTML link.
     * @since 1.0.0
     *
     */
    private function build_row_actions( $item, int $item_id ): array {
        $actions = [];

        foreach ( $this->config['row_actions'] as $key => $action ) {
            if ( ! is_array( $action ) ) {
                continue;
            }

            // Check condition callback
            if ( isset( $action['condition'] ) && is_callable( $action['condition'] ) ) {
                if ( ! call_user_func( $action['condition'], $item ) ) {
                    continue;
                }
            }

            // Check capability
            if ( isset( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                continue;
            }

            $action_html = $this->build_single_action( $action, $item, $item_id, $key );

            if ( ! empty( $action_html ) ) {
                $actions[ $key ] = $action_html;
            }
        }

        return $actions;
    }

/**
     * Build a single row action link
     *
     * Generates HTML for a single row action based on its type:
     * - callback: Custom HTML from callable
     * - flyout: Opens a flyout panel (requires wp-flyout library)
     * - handler: Server-side handler with automatic nonce
     * - url: Simple link to URL
     *
     * @param array  $action  Action configuration array.
     * @param object $item    Data object.
     * @param int    $item_id Item ID.
     * @param string $key     Action identifier.
     *
     * @return string Action link HTML.
     * @since 1.0.0
     *
     */
    private function build_single_action( array $action, $item, int $item_id, string $key ): string {
        // Custom callback — full control over output
        if ( isset( $action['callback'] ) && is_callable( $action['callback'] ) ) {
            return call_user_func( $action['callback'], $item );
        }

        // Flyout action — opens edit flyout panel
        if ( isset( $action['flyout'] ) && $action['flyout'] === true && ! empty( $this->config['flyouts']['edit'] ) ) {
            if ( function_exists( 'get_flyout_link' ) ) {
                return \get_flyout_link( $this->config['flyouts']['edit'], [
					'id'   => $item_id,
					'text' => $action['label'] ?? ucfirst( $key ),
                ] );
            }

            return sprintf( '<a href="#">%s</a>', esc_html( $action['label'] ?? ucfirst( $key ) ) );
        }

        // Handler-based action — processed by Manager::process_single_action()
        if ( isset( $action['handler'] ) && is_callable( $action['handler'] ) ) {
            $singular     = $this->config['labels']['singular'] ?? 'item';
            $nonce_action = $action['nonce_action'] ?? "{$key}_{$singular}_{$item_id}";
            $nonce_action = str_replace( '{id}', (string) $item_id, $nonce_action );

            $url = wp_nonce_url(
                    add_query_arg(
                            [
								'action' => $key,
								'item'   => $item_id,
                            ],
                            $this->get_current_url()
                    ),
                    $nonce_action
            );

            $class = $action['class'] ?? '';
            $attrs = '';

            // Confirmation dialog
            if ( ! empty( $action['confirm'] ) ) {
                $confirm_msg = is_callable( $action['confirm'] )
                        ? call_user_func( $action['confirm'], $item )
                        : $action['confirm'];
                $attrs       .= sprintf( ' onclick="return confirm(\'%s\')"', esc_js( $confirm_msg ) );
            }

            // Dynamic label
            $label = $action['label'] ?? ucfirst( $key );
            if ( is_callable( $label ) ) {
                $label = call_user_func( $label, $item );
            }

            return sprintf(
                    '<a href="%s" class="%s"%s>%s</a>',
                    esc_url( $url ),
                    esc_attr( $class ),
                    $attrs,
                    esc_html( $label )
            );
        }

        // URL-based action — simple link
        if ( isset( $action['url'] ) ) {
            $url = is_callable( $action['url'] )
                    ? call_user_func( $action['url'], $item )
                    : $action['url'];

            $class = $action['class'] ?? '';
            $attrs = '';

            // Confirmation dialog
            if ( ! empty( $action['confirm'] ) ) {
                $confirm_msg = is_string( $action['confirm'] )
                        ? $action['confirm']
                        : __( 'Are you sure?', 'arraypress' );
                $attrs       .= sprintf( ' onclick="return confirm(\'%s\')"', esc_js( $confirm_msg ) );
            }

            return sprintf(
                    '<a href="%s" class="%s"%s>%s</a>',
                    esc_url( $url ),
                    esc_attr( $class ),
                    $attrs,
                    esc_html( $action['label'] ?? ucfirst( $key ) )
            );
        }

        return '';
    }

/**
     * Check if auto delete action should be added
     *
     * Automatically adds a delete row action when a delete callback exists
     * and no explicit delete action is already defined. To prevent the auto
     * delete action, define your own 'delete' row action or omit the delete
     * callback from the callbacks configuration.
     *
     * @param array $actions Current row actions.
     *
     * @return bool True if auto delete should be added.
     * @since 1.0.0
     *
     */
    private function should_add_auto_delete_action( array $actions ): bool {
        // Don't add if an explicit delete action already exists
        if ( isset( $actions['delete'] ) ) {
            return false;
        }

        // Don't add if no delete callback configured
        if ( ! isset( $this->config['callbacks']['delete'] ) || ! is_callable( $this->config['callbacks']['delete'] ) ) {
            return false;
        }

        // Don't add if user lacks delete capability
        if ( ! empty( $this->config['capabilities']['delete'] ) ) {
            if ( ! current_user_can( $this->config['capabilities']['delete'] ) ) {
                return false;
            }
        }

        return true;
    }

/**
     * Build auto delete action HTML
     *
     * Generates a delete action link with nonce and confirmation dialog.
     *
     * @param int $item_id Item ID to delete.
     *
     * @return string Delete action link HTML.
     * @since 1.0.0
     *
     */
    private function build_delete_action( int $item_id ): string {
        $singular = $this->config['labels']['singular'] ?? 'item';

        $delete_url = wp_nonce_url(
                add_query_arg(
                        [
							'action' => 'delete',
							'item'   => $item_id,
                        ],
                        $this->get_current_url()
                ),
                "delete_{$singular}_{$item_id}"
        );

        $confirm_message = sprintf(
        /* translators: %s: singular item label */
                __( 'Are you sure you want to delete this %s?', 'arraypress' ),
                $singular
        );

        return sprintf(
                '<a href="%s" class="delete-link" onclick="return confirm(\'%s\')">%s</a>',
                esc_url( $delete_url ),
                esc_js( $confirm_message ),
                esc_html__( 'Delete', 'arraypress' )
        );
    }
}
