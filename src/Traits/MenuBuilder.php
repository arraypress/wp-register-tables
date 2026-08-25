<?php
/**
 * Menu Registration
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
 * Putting the table's page in the admin menu, and keeping it highlighted.
 *
 * The highlighting is the awkward half. A table page reached from a row
 * action or a flyout is still that table's page, but core decides what to
 * highlight from the query string alone and gets it wrong the moment a page
 * is not where its slug says it is — so the parent and the submenu are
 * corrected by hand.
 */
trait MenuBuilder {

    /**
     * Register admin menu pages for all tables
     *
     * Hooks into admin_menu to create menu/submenu pages for each registered
     * table. Tables with a parent_slug are registered as submenu pages,
     * otherwise as top-level menu pages.
     *
     * @return void
     * @since 2.0.0
     *
     */
    public static function register_menus(): void {
        foreach ( self::$tables as $id => $config ) {
            self::register_menu( $id, $config );
        }
    }

    /**
     * Register a single admin menu page
     *
     * Creates either a top-level menu page or submenu page based on the
     * presence of parent_slug in the configuration.
     *
     * @param string $id     Table identifier.
     * @param array  $config Table configuration.
     *
     * @return void
     * @since 2.0.0
     *
     */
    private static function register_menu( string $id, array $config ): void {
        $render_callback = function () use ( $id ) {
            self::render_table( $id );
        };

        if ( ! empty( $config['parent_slug'] ) ) {
            $hook_suffix = add_submenu_page(
                    $config['parent_slug'],
                    $config['page_title'],
                    $config['menu_title'],
                    $config['capability'],
                    $config['menu_slug'],
                    $render_callback
            );
        } else {
            $hook_suffix = add_menu_page(
                    $config['page_title'],
                    $config['menu_title'],
                    $config['capability'],
                    $config['menu_slug'],
                    $render_callback,
                    $config['icon'],
                    $config['position']
            );
        }

        if ( $hook_suffix ) {
            self::$hook_suffixes[ $id ] = $hook_suffix;
        }
    }

    /**
     * Fix parent menu highlight for submenu table pages
     *
     * Ensures the correct parent menu item is highlighted when viewing
     * a table registered as a submenu page.
     *
     * @param string $parent_file The parent file.
     *
     * @return string
     * @since 2.0.0
     *
     */
    public static function fix_parent_menu_highlight( string $parent_file ): string {
        global $plugin_page;

        foreach ( self::$tables as $id => $config ) {
            if ( empty( $config['parent_slug'] ) ) {
                continue;
            }

            if ( $plugin_page === $config['menu_slug'] ) {
                return $config['parent_slug'];
            }
        }

        return $parent_file;
    }

    /**
     * Fix submenu highlight for table pages
     *
     * Ensures the correct submenu item is highlighted when viewing
     * a table registered as a submenu page.
     *
     * @param string|null $submenu_file The submenu file.
     *
     * @return string|null
     * @since 2.0.0
     *
     */
    public static function fix_submenu_highlight( ?string $submenu_file ): ?string {
        global $plugin_page;

        foreach ( self::$tables as $id => $config ) {
            if ( empty( $config['parent_slug'] ) ) {
                continue;
            }

            if ( $plugin_page === $config['menu_slug'] ) {
                return $config['menu_slug'];
            }
        }

        return $submenu_file;
    }
}
