<?php
/**
 * Parent Menu Registration Manager
 *
 * Manages "transparent" parent menu pages in WordPress admin. These are
 * top-level menu items that serve only as containers for submenu pages
 * registered elsewhere (e.g., by register_admin_table() or other libraries).
 *
 * The class automatically removes the auto-generated first submenu item
 * that WordPress creates to match the parent, so the first real submenu
 * page becomes the default landing page.
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class ParentMenu
 *
 * Static manager for registering parent-only admin menu pages.
 *
 * ## Basic Usage
 *
 * ```php
 * // Register a parent menu — submenu pages are added by other code
 * ParentMenu::register( 'my-plugin', 'My Plugin', [
 *     'icon'     => 'dashicons-admin-generic',
 *     'position' => 30,
 * ] );
 * ```
 *
 * Or use the helper function:
 *
 * ```php
 * register_parent_menu( 'my-plugin', 'My Plugin', [
 *     'icon'     => 'dashicons-cart',
 *     'position' => 30,
 * ] );
 * ```
 *
 * ## How It Works
 *
 * WordPress requires a callback for add_menu_page(), and automatically
 * creates a submenu item matching the parent slug. This class:
 *
 * 1. Registers the top-level page with a no-op callback (__return_null)
 * 2. Removes the auto-generated first submenu item at priority 999
 *
 * The result is a clean parent menu where the first submenu page
 * registered by your plugin (e.g., via register_admin_table()) becomes
 * the default landing page.
 *
 * @since 1.0.0
 */
class ParentMenu {

	/**
	 * Registered parent menus
	 *
	 * Associative array of menu slug => configuration pairs.
	 * Populated by register() calls.
	 *
	 * @since 1.0.0
	 * @var array<string, array>
	 */
	private static array $menus = [];

	/**
	 * Initialization flag
	 *
	 * Prevents duplicate hook registration when multiple parent
	 * menus are registered.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * Register a parent menu
	 *
	 * Creates a top-level admin menu page that serves as a container
	 * for submenu pages. The auto-generated first submenu item is
	 * automatically removed.
	 *
	 * @param string  $slug       Menu slug. Used as the parent_slug when registering
	 *                            submenu pages elsewhere.
	 * @param string  $title      Display title for the menu item.
	 * @param array   $args       {
	 *                            Optional. Configuration arguments.
	 *
	 * @type string   $capability Capability required to see the menu. Default 'manage_options'.
	 * @type string   $icon       Dashicon class or icon URL. Default 'dashicons-admin-generic'.
	 * @type int|null $position   Menu position. Default null (WordPress default ordering).
	 *                            }
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register( string $slug, string $title, array $args = [] ): void {
		self::init();

		self::$menus[ $slug ] = wp_parse_args( $args, [
			'title'      => $title,
			'capability' => 'manage_options',
			'icon'       => 'dashicons-admin-generic',
			'position'   => null,
		] );
	}

	/**
	 * Unregister a parent menu
	 *
	 * Removes a parent menu from the registry before it is rendered.
	 * Must be called before the admin_menu hook fires.
	 *
	 * @param string $slug Menu slug to remove.
	 *
	 * @return bool True if removed, false if not found.
	 * @since 1.0.0
	 */
	public static function unregister( string $slug ): bool {
		if ( isset( self::$menus[ $slug ] ) ) {
			unset( self::$menus[ $slug ] );

			return true;
		}

		return false;
	}

	/**
	 * Check if a parent menu is registered
	 *
	 * @param string $slug Menu slug to check.
	 *
	 * @return bool True if registered.
	 * @since 1.0.0
	 */
	public static function has( string $slug ): bool {
		return isset( self::$menus[ $slug ] );
	}

	/**
	 * Get a registered parent menu configuration
	 *
	 * @param string $slug Menu slug.
	 *
	 * @return array|null Menu configuration or null if not found.
	 * @since 1.0.0
	 */
	public static function get( string $slug ): ?array {
		return self::$menus[ $slug ] ?? null;
	}

	/**
	 * Get all registered parent menus
	 *
	 * @return array<string, array> All registered menu configurations.
	 * @since 1.0.0
	 */
	public static function get_all(): array {
		return self::$menus;
	}

	/**
	 * Initialize the manager
	 *
	 * Hooks into WordPress admin_menu to register pages and fix
	 * the auto-generated submenu items. Called automatically on
	 * first register() call.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_menu', [ __CLASS__, 'fix_menus' ], 999 );
	}

	/**
	 * Register all parent menu pages
	 *
	 * Hooked to admin_menu. Creates top-level menu pages for each
	 * registered parent menu using __return_null as the render callback.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register_menus(): void {
		foreach ( self::$menus as $slug => $args ) {
			add_menu_page(
				$args['title'],
				$args['title'],
				$args['capability'],
				$slug,
				'__return_null',
				$args['icon'],
				$args['position']
			);
		}
	}

	/**
	 * Fix auto-generated submenu items
	 *
	 * WordPress automatically creates a submenu item matching the
	 * parent menu slug. This removes those entries so the first
	 * real submenu page becomes the default landing page.
	 *
	 * Hooked to admin_menu at priority 999 to run after all submenu
	 * pages have been registered.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function fix_menus(): void {
		global $submenu;

		foreach ( self::$menus as $slug => $args ) {
			if ( isset( $submenu[ $slug ] ) ) {
				unset( $submenu[ $slug ][0] );
				$submenu[ $slug ] = array_values( $submenu[ $slug ] );
			}
		}
	}

}