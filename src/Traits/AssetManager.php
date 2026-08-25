<?php
/**
 * Asset Loading
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
use ArrayPress\FieldKit\Assets;

/**
 * Loading the stylesheet and script, and only where a table is.
 *
 * Admin CSS loaded on every screen is how a library becomes something people
 * remove, so the enqueue is gated on the screen belonging to a registered
 * table. The dynamic styles are the per-table column widths, which cannot be
 * in a static file because they come from configuration.
 */
trait AssetManager {

    /**
     * Enqueue assets callback
     *
     * Hooked to admin_enqueue_scripts. Checks if current page matches
     * a registered table and enqueues styles if so.
     *
     * @param string $hook Current admin page hook suffix.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function enqueue_assets( string $hook ): void {
        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $config ) {
            if ( ( $config['menu_slug'] ?? '' ) === $page ) {
                self::do_enqueue_assets( $config );
                break;
            }
        }
    }

    /**
     * Actually enqueue the assets
     *
     * Enqueues the main stylesheet and outputs dynamic inline styles
     * for column widths and alignments.
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function do_enqueue_assets( array $config ): void {
        if ( self::$assets_enqueued ) {
            return;
        }

        self::$assets_enqueued = true;

        // The kit's stylesheet, because the header is the kit's. Without it
        // the markup renders and none of the rules for it load, so the header
        // falls back to core's .privacy-settings-header — centred, with an
        // unstyled badge — which is exactly what a settings page looks like
        // and exactly what a list table should not.
        ( new Assets() )->enqueue();

        // Enqueue CSS from composer assets package
        wp_enqueue_composer_style(
                'list-table-styles',
                __FILE__,
                'css/admin-tables.css'
        );

        // Output dynamic styles for this table's configuration
        self::output_dynamic_styles( $config );
    }

    /**
     * Output dynamic inline styles
     *
     * Generates CSS for custom column widths and text alignments
     * based on the column configuration.
     *
     * @param array $config Table configuration.
     *
     * @return void
     * @since 2.0.0
     *
     */
    private static function output_dynamic_styles( array $config ): void {
        $styles = '';

        if ( ! empty( $config['columns'] ) ) {
            foreach ( $config['columns'] as $column => $col_config ) {
                if ( ! is_array( $col_config ) ) {
                    continue;
                }

                // Column width
                if ( ! empty( $col_config['width'] ) ) {
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { width: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $col_config['width'] )
                    );
                }

                // Column alignment
                if ( ! empty( $col_config['align'] ) ) {
                    $align  = in_array( $col_config['align'], [ 'left', 'center', 'right' ], true )
                            ? $col_config['align']
                            : 'left';
                    $styles .= sprintf(
                            ".wp-list-table .column-%s { text-align: %s; }\n",
                            esc_attr( $column ),
                            esc_attr( $align )
                    );
                }
            }
        }

        if ( ! empty( $styles ) ) {
            wp_add_inline_style( 'list-table-styles', $styles );
        }
    }
}
