<?php
/**
 * WordPress Composer Assets Helper Functions
 *
 * Global helper functions for registering and enqueuing assets from Composer
 * packages.
 *
 * These deliberately live in the global namespace, mirroring WordPress' own
 * style, because that is how they read at the call site. They are named
 * arraypress_* rather than wp_*: the wp_ prefix belongs to WordPress core, and
 * a third-party package should not claim names in it. The wp_* spellings are
 * retained below as deprecated aliases.
 *
 * @package     ArrayPress\WP\ComposerAssets
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     2.1.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

use ArrayPress\ComposerAssets\AssetLoader;

if ( ! function_exists( 'arraypress_enqueue_composer_script' ) ) :
	/**
	 * Enqueue a JavaScript file from a Composer package
	 *
	 * Mirrors wp_enqueue_script() but resolves file paths relative to Composer
	 * packages. Automatically detects the assets directory and handles URL
	 * generation.
	 *
	 * @param string      $handle       Script handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to JS file from assets/ directory.
	 * @param array       $deps         Optional. Script dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param array|bool  $args         Optional. Bool for $in_footer, or an array accepting
	 *                                  'in_footer' and 'strategy' ('defer'|'async'). Default true.
	 *
	 * @return bool True on success, false on failure.
	 */
	function arraypress_enqueue_composer_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		$args = true
	): bool {
		return AssetLoader::enqueue_script( $handle, $calling_file, $file, $deps, $ver, $args );
	}
endif;

if ( ! function_exists( 'arraypress_enqueue_composer_style' ) ) :
	/**
	 * Enqueue a CSS file from a Composer package
	 *
	 * @param string      $handle       Style handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to CSS file from assets/ directory.
	 * @param array       $deps         Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. Media for which this stylesheet is defined. Default 'all'.
	 *
	 * @return bool True on success, false on failure.
	 */
	function arraypress_enqueue_composer_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return AssetLoader::enqueue_style( $handle, $calling_file, $file, $deps, $ver, $media );
	}
endif;

if ( ! function_exists( 'arraypress_register_composer_script' ) ) :
	/**
	 * Register a JavaScript file from a Composer package
	 *
	 * The script can be enqueued later with wp_enqueue_script() and the same handle.
	 *
	 * @param string      $handle       Script handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to JS file from assets/ directory.
	 * @param array       $deps         Optional. Script dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param array|bool  $args         Optional. Bool for $in_footer, or an array accepting
	 *                                  'in_footer' and 'strategy' ('defer'|'async'). Default true.
	 *
	 * @return bool True on success, false on failure.
	 */
	function arraypress_register_composer_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		$args = true
	): bool {
		return AssetLoader::register_script( $handle, $calling_file, $file, $deps, $ver, $args );
	}
endif;

if ( ! function_exists( 'arraypress_register_composer_style' ) ) :
	/**
	 * Register a CSS file from a Composer package
	 *
	 * The style can be enqueued later with wp_enqueue_style() and the same handle.
	 *
	 * @param string      $handle       Style handle for registration.
	 * @param string      $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string      $file         Relative path to CSS file from assets/ directory.
	 * @param array       $deps         Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. Media for which this stylesheet is defined. Default 'all'.
	 *
	 * @return bool True on success, false on failure.
	 */
	function arraypress_register_composer_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return AssetLoader::register_style( $handle, $calling_file, $file, $deps, $ver, $media );
	}
endif;

if ( ! function_exists( 'arraypress_get_composer_file' ) ) :
	/**
	 * Get any file's contents from a Composer package
	 *
	 * Generic loader for any asset type (SVG, JSON, XML, ...).
	 *
	 * @param string $calling_file File path to resolve assets relative to. Use __FILE__.
	 * @param string $file         Relative path to file from assets/ directory.
	 *
	 * @return string|false File content on success, false on failure.
	 */
	function arraypress_get_composer_file( string $calling_file, string $file ) {
		return AssetLoader::get_file( $calling_file, $file );
	}
endif;

/*
 * Deprecated wp_*-prefixed aliases.
 *
 * Retained so existing callers keep working. New code should use the
 * arraypress_* names above — wp_ is WordPress core's prefix, and a package
 * that squats it risks colliding with a future core function, in which case
 * the function_exists() guard would silently hand callers core's
 * implementation instead of this one.
 *
 * Declared explicitly rather than generated: Strauss rewrites function names
 * by parsing declarations, so anything built dynamically would escape
 * prefixing and reintroduce exactly the global-name collision these aliases
 * exist to avoid.
 */

if ( ! function_exists( 'wp_enqueue_composer_script' ) ) :
	/**
	 * @deprecated 2.1.0 Use arraypress_enqueue_composer_script().
	 *
	 * @param string      $handle       Script handle.
	 * @param string      $calling_file Calling file. Use __FILE__.
	 * @param string      $file         Relative path from assets/.
	 * @param array       $deps         Dependencies.
	 * @param string|bool $ver          Version, or false to auto-detect.
	 * @param array|bool  $args         $in_footer bool, or args array.
	 *
	 * @return bool
	 */
	function wp_enqueue_composer_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		$args = true
	): bool {
		return arraypress_enqueue_composer_script( $handle, $calling_file, $file, $deps, $ver, $args );
	}
endif;

if ( ! function_exists( 'wp_enqueue_composer_style' ) ) :
	/**
	 * @deprecated 2.1.0 Use arraypress_enqueue_composer_style().
	 *
	 * @param string      $handle       Style handle.
	 * @param string      $calling_file Calling file. Use __FILE__.
	 * @param string      $file         Relative path from assets/.
	 * @param array       $deps         Dependencies.
	 * @param string|bool $ver          Version, or false to auto-detect.
	 * @param string      $media        Media type.
	 *
	 * @return bool
	 */
	function wp_enqueue_composer_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return arraypress_enqueue_composer_style( $handle, $calling_file, $file, $deps, $ver, $media );
	}
endif;

if ( ! function_exists( 'wp_register_composer_script' ) ) :
	/**
	 * @deprecated 2.1.0 Use arraypress_register_composer_script().
	 *
	 * @param string      $handle       Script handle.
	 * @param string      $calling_file Calling file. Use __FILE__.
	 * @param string      $file         Relative path from assets/.
	 * @param array       $deps         Dependencies.
	 * @param string|bool $ver          Version, or false to auto-detect.
	 * @param array|bool  $args         $in_footer bool, or args array.
	 *
	 * @return bool
	 */
	function wp_register_composer_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		$args = true
	): bool {
		return arraypress_register_composer_script( $handle, $calling_file, $file, $deps, $ver, $args );
	}
endif;

if ( ! function_exists( 'wp_register_composer_style' ) ) :
	/**
	 * @deprecated 2.1.0 Use arraypress_register_composer_style().
	 *
	 * @param string      $handle       Style handle.
	 * @param string      $calling_file Calling file. Use __FILE__.
	 * @param string      $file         Relative path from assets/.
	 * @param array       $deps         Dependencies.
	 * @param string|bool $ver          Version, or false to auto-detect.
	 * @param string      $media        Media type.
	 *
	 * @return bool
	 */
	function wp_register_composer_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		return arraypress_register_composer_style( $handle, $calling_file, $file, $deps, $ver, $media );
	}
endif;

if ( ! function_exists( 'wp_get_composer_file' ) ) :
	/**
	 * @deprecated 2.1.0 Use arraypress_get_composer_file().
	 *
	 * @param string $calling_file Calling file. Use __FILE__.
	 * @param string $file         Relative path from assets/.
	 *
	 * @return string|false
	 */
	function wp_get_composer_file( string $calling_file, string $file ) {
		return arraypress_get_composer_file( $calling_file, $file );
	}
endif;
