<?php
/**
 * Bulk Actions
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\RegisterTables\Table;

/**
 * Acting on a set of rows at once.
 *
 * Separated from the single-row handler because it is one long method with a
 * different shape: the ids arrive as an array, the capability check is the
 * one declared for bulk rather than for the individual action, and the
 * outcome is a count rather than a yes or no.
 *
 * The capability is checked against the rows being processed, not against the
 * buttons being drawn. Enforcing it only where the menu is built leaves the
 * endpoint open to anyone who can post to it.
 */
trait BulkProcessing {

/**
     * Process bulk actions
     *
     * Handles bulk action form submissions. Verifies nonce, checks capability,
     * executes callback (if defined), fires hooks, and redirects.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function process_bulk_actions( string $id, array $config ): void {
        // Determine which bulk action was selected
        $action = '';
        // Which action was chosen, and from which end of the table. Read
        // before the nonce because the nonce action is not known until the
        // bulk action is; verified immediately below, before anything is
        // done with it.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
        } elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
        }

        if ( empty( $action ) ) {
            return;
        }

        // Whether this user may run a bulk action at all.
        //
        // Refused the same way the per-action check and the delete path
        // refuse: told, rather than quietly ignored. A silently dropped
        // action is indistinguishable from one that succeeded and did
        // nothing.
        //
        // get_bulk_actions() checks the same capability, but that decides
        // whether the dropdown is *drawn* — which is hiding the control
        // rather than refusing the action. The nonce below is a CSRF check
        // and not an authorisation one, and it is scoped to the plural label,
        // so two tables sharing one accept each other's. Anyone who could see
        // the page could run its bulk actions.
        if ( ! empty( $config['capabilities']['bulk'] )
            && ! current_user_can( $config['capabilities']['bulk'] ) ) {
            wp_die( esc_html__( 'Sorry, you are not allowed to perform this action.', 'arraypress' ) );
        }

        $plural = $config['labels']['plural'] ?? 'items';

        // Verify nonce
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is the check.
        if ( ! isset( $_REQUEST['_wpnonce'] ) ||
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-' . $plural ) ) {
            return;
        }

        // Get selected items. The checkboxes post an array of ids, so each
        // one is sanitized rather than the array: sanitize_text_field() hands
        // back an empty string when it is given an array, which would drop
        // every selection and make each bulk action quietly do nothing.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately above.
        $items = isset( $_REQUEST[ $plural ] )
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                ? array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST[ $plural ] ) )
                : [];

        if ( empty( $items ) ) {
            return;
        }

        $items = array_map( 'absint', $items );

        // Get action configuration
        $action_config = $config['bulk_actions'][ $action ] ?? null;

        if ( ! $action_config ) {
            return;
        }

        // Normalize string config to array
        if ( is_string( $action_config ) ) {
            $action_config = [ 'label' => $action_config ];
        }

        // Check capability
        if ( isset( $action_config['capability'] ) ) {
            if ( ! current_user_can( $action_config['capability'] ) ) {
                wp_die( esc_html__( 'Sorry, you are not allowed to perform this action.', 'arraypress' ) );
            }
        }

        /**
         * Fires when a bulk action is processed on a specific table
         *
         * @param array  $items  Selected item IDs.
         * @param string $action Bulk action key.
         *
         * @since 1.0.0
         *
         */
        do_action( "arraypress_table_bulk_action_{$id}", $items, $action );

        /**
         * Fires for a specific bulk action on a specific table
         *
         * @param array $items Selected item IDs.
         *
         * @since 1.0.0
         *
         */
        do_action( "arraypress_table_bulk_action_{$id}_{$action}", $items );

        // Execute callback if defined
        $redirect_args = [];

        if ( isset( $action_config['callback'] ) && is_callable( $action_config['callback'] ) ) {
            $result = call_user_func( $action_config['callback'], $items );

            // Handle different return types
            if ( is_array( $result ) ) {
                $redirect_args = $result;
            } elseif ( is_int( $result ) ) {
                $redirect_args = [ 'updated' => $result ];
            } elseif ( is_bool( $result ) ) {
                $redirect_args = [ 'updated' => $result ? count( $items ) : 0 ];
            }
        } else {
            // No callback — assume success
            $redirect_args = [ 'updated' => count( $items ) ];
        }

        // Add bulk action key for notice lookup
        $redirect_args['_bulk_action'] = $action;

        // Redirect with results
        $redirect_url = self::get_clean_base_url( $config );

        if ( ! empty( $redirect_args ) ) {
            $redirect_url = add_query_arg( $redirect_args, $redirect_url );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }
}
