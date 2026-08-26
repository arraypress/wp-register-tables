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

use ArrayPress\RegisterTables\Request;
use ArrayPress\RegisterTables\Table;

/**
 * Acting on a single row, before anything is drawn.
 *
 * Early, because these all end in a redirect — an action that has already
 * sent output cannot redirect, and a page that renders before it acts shows
 * the row it is about to delete.
 *
 * Every path checks the capability the table declared for it and a nonce
 * named for the action and the row. A row action is a link, so without both
 * it is a URL anyone can be talked into visiting.
 */
trait ActionProcessing {

    /**
     * Process early actions
     *
     * Handles actions that require redirects before any output is sent.
     * This includes filter form submissions, single item actions (delete),
     * and bulk actions.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function process_early_actions(): void {
        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( $config['menu_slug'] === $page ) {
                // Process in order of priority
                self::process_filter_redirect( $id, $config );
                self::process_single_actions( $id, $config );
                self::process_bulk_actions( $id, $config );
                break;
            }
        }
    }

    /**
     * Process filter form submission
     *
     * When filters are submitted, redirects to a clean URL with only
     * the necessary parameters (removes _wpnonce, filter_action, etc.).
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function process_filter_redirect( string $id, array $config ): void {
        if ( ! Request::has( 'filter_action' ) ) {
            return;
        }

        $clean_args = [
			'page' => $config['menu_slug'],
        ];

        // Preserve search
        if ( Request::filled( 's' ) ) {
            $clean_args['s'] = Request::text( 's' );
        }

        // Preserve status
        if ( Request::filled( 'status' ) ) {
            $clean_args['status'] = Request::key( 'status' );
        }

        // Preserve the sort, which the form now carries and which would
        // otherwise be dropped by the very redirect meant to tidy the URL.
        foreach ( [ 'orderby', 'order' ] as $key ) {
            if ( Request::filled( $key ) ) {
                $clean_args[ $key ] = Request::key( $key );
            }
        }

        // Preserve custom filters
        foreach ( $config['filters'] as $filter_key => $filter ) {
            if ( Request::filled( $filter_key ) ) {
                $clean_args[ $filter_key ] = Request::text( $filter_key );
            }
        }

        wp_safe_redirect( self::page_url( $config, $clean_args ) );
        exit;
    }

    /**
     * Process single item actions
     *
     * Handles row actions like delete. Checks for handler-based actions
     * in the row_actions config, or falls back to the built-in delete action.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function process_single_actions( string $id, array $config ): void {
        $action  = Request::key( 'action' );
        $item_id = Request::count( 'item' );

        if ( empty( $action ) || empty( $item_id ) ) {
            return;
        }

        // Skip bulk action placeholder values
        if ( $action === '-1' ) {
            return;
        }

        // Handle built-in delete action
        if ( $action === 'delete' ) {
            self::handle_delete_action( $id, $config, $item_id );

            return;
        }

        // Check for custom handler in row_actions config
        if ( ! is_callable( $config['row_actions'] ) && isset( $config['row_actions'][ $action ] ) ) {
            $action_config = $config['row_actions'][ $action ];

            if ( is_array( $action_config ) && isset( $action_config['handler'] ) && is_callable( $action_config['handler'] ) ) {
                self::handle_custom_action( $id, $config, $action, $action_config, $item_id );

                return;
            }
        }

        /**
         * Fires when a custom single action is triggered without a handler
         *
         * Use this hook to handle custom row actions that don't have
         * a handler defined in the row_actions config.
         *
         * @param string $action  Action key being performed.
         * @param int    $item_id Item ID the action is performed on.
         * @param array  $config  Table configuration.
         *
         * @since 1.0.0
         *
         */
        do_action( "arraypress_table_single_action_{$id}", $action, $item_id, $config );
    }

    /**
     * Handle custom row action with handler callback
     *
     * Processes a row action that has a 'handler' callback defined.
     * Verifies nonce, checks capability, calls handler, and redirects.
     *
     * @param string $id            Table identifier.
     * @param array  $config        Table configuration.
     * @param string $action        Action key.
     * @param array  $action_config Action configuration from row_actions.
     * @param int    $item_id       Item ID.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function handle_custom_action(
        string $id,
        array $config,
        string $action,
        array $action_config,
        int $item_id
    ): void {
        $singular = $config['labels']['singular'] ?? 'item';

        // Determine nonce action string
        $nonce_action = $action_config['nonce_action'] ?? "{$action}_{$singular}_{$item_id}";
        $nonce_action = str_replace( '{id}', (string) $item_id, $nonce_action );

        // Verify nonce
        // The nonce itself, read before it can be verified. Every other
        // query argument this method uses is read after the check below.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $nonce = isset( $_GET['_wpnonce'] )
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) )
                : '';
        if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
            wp_die( esc_html__( 'Security check failed.', 'arraypress' ) );
        }

        // Check capability
        if ( ! empty( $action_config['capability'] ) ) {
            if ( ! current_user_can( $action_config['capability'] ) ) {
                wp_die( esc_html__( 'You do not have permission to perform this action.', 'arraypress' ) );
            }
        }

        // Call the handler
        $result = call_user_func( $action_config['handler'], $item_id, $config );

        // Build redirect URL with result
        $redirect_url = self::get_clean_base_url( $config );

        // Add action key for notice lookup
        $redirect_url = add_query_arg( '_row_action', $action, $redirect_url );

        if ( is_array( $result ) ) {
            $redirect_url = add_query_arg( $result, $redirect_url );
        } elseif ( $result === true ) {
            $redirect_url = add_query_arg( 'updated', 1, $redirect_url );
        } elseif ( $result === false ) {
            $redirect_url = add_query_arg( 'error', 'action_failed', $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Handle built-in delete action
     *
     * Processes delete requests using the configured delete callback.
     * Verifies nonce, checks capability, calls callback, and redirects.
     *
     * @param string $id      Table identifier.
     * @param array  $config  Table configuration.
     * @param int    $item_id Item ID to delete.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function handle_delete_action( string $id, array $config, int $item_id ): void {
        // Ensure delete callback exists
        if ( ! isset( $config['callbacks']['delete'] ) || ! is_callable( $config['callbacks']['delete'] ) ) {
            return;
        }

        $singular = $config['labels']['singular'] ?? 'item';

        // Verify nonce
        // The nonce itself, read before it can be verified. Every other
        // query argument this method uses is read after the check below.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $nonce = isset( $_GET['_wpnonce'] )
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) )
                : '';
        if ( ! wp_verify_nonce( $nonce, "delete_{$singular}_{$item_id}" ) ) {
            wp_die( esc_html__( 'Security check failed.', 'arraypress' ) );
        }

        // Check capability
        if ( ! empty( $config['capabilities']['delete'] ) ) {
            if ( ! current_user_can( $config['capabilities']['delete'] ) ) {
                wp_die( esc_html__( 'You do not have permission to delete this item.', 'arraypress' ) );
            }
        }

        // Perform deletion
        $result = call_user_func( $config['callbacks']['delete'], $item_id );

        /**
         * Fires after a single item is deleted from a specific table
         *
         * @param int   $item_id Item ID that was deleted.
         * @param mixed $result  Result from delete callback.
         * @param array $config  Table configuration.
         *
         * @since 1.0.0
         *
         */
        do_action( "arraypress_table_item_deleted_{$id}", $item_id, $result, $config );

        // Redirect with result
        $redirect_url = self::get_clean_base_url( $config );
        $redirect_url = add_query_arg( 'deleted', $result ? 1 : 0, $redirect_url );

        wp_safe_redirect( $redirect_url );
        exit;
    }
}
