<?php
/**
 * Screen Setup
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
use WP_Screen;

/**
 * What has to be arranged before the page renders.
 *
 * Column options, per-page counts and help tabs all hang off the load hook
 * for the table's own screen: they are read by core while the page is being
 * built, so registering them any later means registering them for the next
 * request instead of this one.
 */
trait ScreenSetup {

    /**
     * Setup load hooks for screen options
     *
     * Detects when we're on one of our admin pages and sets up screen
     * options (items per page) and help tabs on the current_screen hook.
     *
     * @return void
     * @since 1.0.0
     *
     */
    public static function setup_load_hooks(): void {
        global $pagenow;

        // Only process on admin.php pages
        if ( $pagenow !== 'admin.php' ) {
            return;
        }

        $page = Request::text( 'page' );

        if ( empty( $page ) ) {
            return;
        }

        // Find matching table config
        foreach ( self::$tables as $id => $config ) {
            if ( ( $config['menu_slug'] ?? '' ) === $page ) {
                // Setup screen options on current_screen hook
                add_action( 'current_screen', function () use ( $id, $config ) {
                    self::setup_screen( $id, $config );
                } );
                break;
            }
        }
    }

    /**
     * Setup screen options and help tabs
     *
     * Adds the "items per page" screen option and any configured help tabs.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function setup_screen( string $id, array $config ): void {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return;
        }

        // Build unique option name for this table
        $option_name = $id . '_per_page';

        // Add per page screen option
        $screen->add_option( 'per_page', [
			'label'   => __( 'Number of items per page:', 'arraypress' ),
			'default' => $config['per_page'],
			'option'  => $option_name,
        ] );

        // Add column visibility options
        self::setup_column_options( $screen, $config );

        /*
         * The three headings core gives every list screen. They are only ever
         * announced, never drawn -- WP_List_Table wraps the views, the
         * pagination and the table itself in <h2 class="screen-reader-text">
         * and reads these -- so without them a screen reader meets three
         * unnamed regions where core names them.
         *
         * core sets these on edit.php, upload.php and every other list it
         * ships. A list built from configuration should not be the one that
         * does not.
         */
        $plural = $config['labels']['plural'] ?? __( 'items', 'arraypress' );

        $screen->set_screen_reader_content(
            [
				'heading_views'      => sprintf(
					/* translators: %s: plural item label */
					__( 'Filter %s list', 'arraypress' ),
					$plural
				),
				'heading_pagination' => sprintf(
					/* translators: %s: plural item label */
					__( '%s list navigation', 'arraypress' ),
					ucfirst( $plural )
				),
				'heading_list'       => sprintf(
					/* translators: %s: plural item label */
					__( '%s list', 'arraypress' ),
					ucfirst( $plural )
				),
            ]
        );

        // Add help tabs if configured
        if ( ! empty( $config['help'] ) ) {
            self::setup_help_tabs( $screen, $config['help'] );
        }
    }

    /**
     * Setup column visibility screen options
     *
     * Registers columns so users can show/hide them via Screen Options.
     *
     * @param WP_Screen $screen Current screen object.
     * @param array     $config Table configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function setup_column_options( $screen, array $config ): void {
        if ( empty( $config['columns'] ) ) {
            return;
        }

        // Build columns array for WP_Screen
        $columns = [];
        foreach ( $config['columns'] as $key => $column ) {
            if ( $key === 'cb' ) {
                continue; // Skip checkbox column
            }

            $label           = is_array( $column ) ? ( $column['label'] ?? $key ) : $column;
            $columns[ $key ] = $label;
        }

        // Store columns for get_column_headers()
        // WordPress uses this to build the column toggle checkboxes
        add_filter( 'manage_' . $screen->id . '_columns', function () use ( $columns ) {
            return $columns;
        } );
    }

    /**
     * Setup help tabs on the screen
     *
     * @param WP_Screen $screen Current screen object.
     * @param array     $help   Help tab configuration.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function setup_help_tabs( $screen, array $help ): void {
        foreach ( $help as $key => $tab ) {
            // Sidebar is special
            if ( $key === 'sidebar' ) {
                $screen->set_help_sidebar( $tab );
                continue;
            }

            if ( ! is_array( $tab ) || ! isset( $tab['title'] ) ) {
                continue;
            }

            // Get content from callback or direct content
            $content = '';
            if ( isset( $tab['callback'] ) && is_callable( $tab['callback'] ) ) {
                $content = call_user_func( $tab['callback'] );
            } elseif ( isset( $tab['content'] ) ) {
                $content = $tab['content'];
            }

            $screen->add_help_tab( [
				'id'      => sanitize_key( $key ),
				'title'   => $tab['title'],
				'content' => $content,
            ] );
        }
    }

    /**
     * Handle screen option saving
     *
     * Adds filters to allow per_page options to be saved.
     * Uses both the generic filter and dynamic filters for compatibility.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private static function handle_screen_options(): void {
        // Generic filter for older WP versions
        add_filter( 'set-screen-option', function ( $status, $option, $value ) {
            // Match our table per_page options (e.g., 'ate_customers_per_page')
            if ( str_ends_with( $option, '_per_page' ) ) {
                return absint( $value );
            }

            return $status;
        }, 10, 3 );
    }
}
