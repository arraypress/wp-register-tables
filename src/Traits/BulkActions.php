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

use ArrayPress\RegisterTables\Manager;

/**
 * The dropdown above the table, and handing what it chose to the manager.
 *
 * Only the offering happens here. Whether the action is allowed is decided
 * again where the rows are actually processed — enforcing a capability only
 * where the menu is drawn leaves the endpoint open to anyone who can post to
 * it.
 */
trait BulkActions {

    /**
     * Get bulk actions
     *
     * Returns array of bulk action options for the dropdown.
     * Respects capability requirements.
     *
     * @return array Bulk action key => label pairs.
     * @since 1.0.0
     *
     */
    public function get_bulk_actions(): array {
        $actions = [];

        // Check bulk capability
        if ( ! empty( $this->config['capabilities']['bulk'] ) ) {
            if ( ! current_user_can( $this->config['capabilities']['bulk'] ) ) {
                return $actions;
            }
        }

        foreach ( $this->config['bulk_actions'] as $key => $action ) {
            if ( is_string( $action ) ) {
                $actions[ $key ] = $action;
            } elseif ( is_array( $action ) && isset( $action['label'] ) ) {
                // Check action-specific capability
                if ( isset( $action['capability'] ) && ! current_user_can( $action['capability'] ) ) {
                    continue;
                }
                $actions[ $key ] = $action['label'];
            }
        }

        /**
         * Filter bulk actions
         *
         * @param array  $actions Bulk action options.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( "arraypress_table_bulk_actions_{$this->id}", $actions, $this->config );
    }
}
