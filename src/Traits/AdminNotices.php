<?php
/**
 * Admin Notices
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
 * What the page says after something happened.
 *
 * Read back out of the query string, because the action that did the work
 * redirected before rendering. Every message is looked up from the table's
 * own labels rather than assembled from fragments, so it can be translated as
 * a sentence — and so that a count of one does not read "1 items".
 */
trait AdminNotices {

/**
     * Render admin notices
     *
     * Displays success/error messages based on URL parameters from action
     * processing. Supports custom notices defined in row_actions and
     * bulk_actions via the 'notice' configuration key.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function render_admin_notices( string $id, array $config ): void {
        $singular = $config['labels']['singular'] ?? 'item';
        $plural   = $config['labels']['plural'] ?? 'items';

        // Row action notices (custom handler results)
        $row_action  = Request::key( '_row_action' );
        $bulk_action = Request::key( '_bulk_action' );

        if ( ! empty( $row_action ) && isset( $config['row_actions'][ $row_action ]['notice'] ) ) {
            self::render_action_notice( $config['row_actions'][ $row_action ]['notice'] );
        } elseif ( empty( $row_action ) && empty( $bulk_action ) ) {
            // Only show generic notices when no row action or bulk action is specified
            // to avoid double notices

            // Deleted notice
            if ( Request::has( 'deleted' ) ) {
                $count = Request::count( 'deleted' );

                if ( $count > 0 ) {
                    $message = sprintf(
                            /* translators: 1: number of items, 2: what they are called, singular or plural */
                            __( '%1$s %2$s deleted successfully.', 'arraypress' ),
                            number_format_i18n( $count ),
                            1 === $count ? $singular : $plural
                    );
                    $type    = 'success';
                } else {
                    $message = __( 'Delete failed. Please try again.', 'arraypress' );
                    $type    = 'error';
                }

                printf(
                        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                        esc_attr( $type ),
                        esc_html( $message )
                );
            }

            // Updated notice (generic, when no action-specific notice exists)
            if ( Request::has( 'updated' ) ) {
                $count = Request::count( 'updated' );

                if ( $count > 0 ) {
                    $message = sprintf(
                            /* translators: 1: number of items, 2: what they are called, singular or plural */
                            __( '%1$s %2$s updated successfully.', 'arraypress' ),
                            number_format_i18n( $count ),
                            1 === $count ? $singular : $plural
                    );

                    printf(
                            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                            esc_html( $message )
                    );
                }
            }
        }

        // Bulk action notices
        if ( ! empty( $bulk_action ) && isset( $config['bulk_actions'][ $bulk_action ] ) ) {
            $bulk_config = $config['bulk_actions'][ $bulk_action ];

            if ( is_array( $bulk_config ) && isset( $bulk_config['notice'] ) ) {
                // Get count from redirect args
                $count = Request::has( 'updated' ) ? Request::count( 'updated' ) : Request::count( 'deleted' );
                self::render_action_notice( $bulk_config['notice'], $count );
            } elseif ( Request::has( 'updated' ) || Request::has( 'deleted' ) ) {
                // Fallback to generic notice for bulk actions without custom notice
                $count = Request::has( 'updated' ) ? Request::count( 'updated' ) : Request::count( 'deleted' );

                if ( $count > 0 ) {
                    $message = sprintf(
                            /* translators: 1: number of items, 2: what they are called, singular or plural */
                            __( '%1$s %2$s updated successfully.', 'arraypress' ),
                            number_format_i18n( $count ),
                            1 === $count ? $singular : $plural
                    );

                    printf(
                            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                            esc_html( $message )
                    );
                }
            }
        }

        // Error notice (always show, not action-specific)
        if ( ( Request::has( 'error' ) && Request::text( 'error' ) !== 'action_failed' ) || ( Request::has( 'error' ) && empty( $row_action ) ) ) {
            $error = Request::text( 'error' );

            if ( ! empty( $error ) && $error !== 'action_failed' ) {
                printf(
                        '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                        esc_html( $error )
                );
            }
        }

        /**
         * Filter custom admin notices for a specific table
         *
         * Starts empty. The unscoped twin of this filter used to seed it, and
         * removing that left the variable undefined — a warning above the
         * table on every request, and every notice a consumer added dropped
         * on the floor.
         *
         * @param array $notices Array of notices (see above for format).
         * @param array $config  Table configuration.
         *
         * @since 1.0.0
         *
         */
        $custom_notices = (array) apply_filters( "arraypress_table_admin_notices_{$id}", [], $config );

        foreach ( $custom_notices as $notice ) {
            if ( empty( $notice['message'] ) ) {
                continue;
            }

            $type        = $notice['type'] ?? 'info';
            $dismissible = $notice['dismissible'] ?? true;
            $class       = 'notice notice-' . esc_attr( $type );

            if ( $dismissible ) {
                $class .= ' is-dismissible';
            }

            printf(
                    '<div class="%s"><p>%s</p></div>',
                    esc_attr( $class ),
                    esc_html( $notice['message'] )
            );
        }
    }

/**
     * Render a notice from action configuration
     *
     * Handles the 'notice' key from row_actions and bulk_actions config.
     * Supports both simple array format and callable format.
     *
     * Simple format:
     * 'notice' => [
     *     'success' => 'Customer status updated.',
     *     'error'   => 'Failed to update status.',
     * ]
     *
     * With count placeholder (for bulk actions):
     * 'notice' => [
     *     'success' => '%d customers activated.',
     *     'error'   => 'Failed to activate customers.',
     * ]
     *
     * @param array|callable $notice_config Notice configuration.
     * @param int            $count         Optional count for bulk action notices.
     *
     * @return void
     * @since 2.0.0
     *
     */
    private static function render_action_notice( $notice_config, int $count = 0 ): void {
        // Callable format — let the callback determine the notice
        if ( is_callable( $notice_config ) ) {
            $notice = call_user_func( $notice_config, Request::all() );
            if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
                $type = $notice['type'] ?? 'success';
                printf(
                        '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                        esc_attr( $type ),
                        esc_html( $notice['message'] )
                );
            }

            return;
        }

        // Array format with success/error keys
        if ( ! is_array( $notice_config ) ) {
            return;
        }

        // Determine success or error based on URL params
        $is_error = Request::has( 'error' ) || ( Request::has( 'updated' ) && 0 === Request::count( 'updated' ) );

        if ( $is_error && ! empty( $notice_config['error'] ) ) {
            printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html( $notice_config['error'] )
            );
        } elseif ( ! $is_error && ! empty( $notice_config['success'] ) ) {
            $message = $notice_config['success'];

            // Replace %d placeholder with count if present
            if ( $count > 0 && str_contains( $message, '%d' ) ) {
                $message = sprintf( $message, $count );
            }

            printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html( $message )
            );
        }
    }
}
