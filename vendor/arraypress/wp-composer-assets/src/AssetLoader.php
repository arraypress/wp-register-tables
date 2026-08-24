<?php
/**
 * Asset Loader Class
 *
 * Handles loading and registration of assets from Composer packages.
 *
 * @package     ArrayPress\WP\ComposerAssets
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     2.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\ComposerAssets;

class AssetLoader {

	/**
	 * How far up to look for a package root before giving up.
	 *
	 * A Composer package is never this deeply nested; the limit exists only so
	 * a pathological path cannot walk to the filesystem root.
	 */
	private const MAX_DEPTH = 12;

	/**
	 * Cached asset paths, keyed by calling file.
	 *
	 * A null value is a cached miss: the package has no assets directory, and
	 * there is no point walking the filesystem again to rediscover that.
	 *
	 * @var array<string, string|null>
	 */
	private static array $path_cache = [];

	/**
	 * Cached asset URLs, keyed by calling file.
	 *
	 * @var array<string, string|null>
	 */
	private static array $url_cache = [];

	/**
	 * Enqueue a JavaScript file from a Composer package
	 *
	 * @param string      $handle       Script handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to JS file from assets/
	 * @param array       $deps         Optional. Script dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param array|bool  $args         Optional. Either a bool for $in_footer, or an array
	 *                                  accepting 'in_footer' (bool) and 'strategy'
	 *                                  ('defer'|'async'), matching wp_enqueue_script()
	 *                                  since WordPress 6.3. Default true.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function enqueue_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		$args = true
	): bool {
		if ( ! self::register_script( $handle, $calling_file, $file, $deps, $ver, $args ) ) {
			return false;
		}

		wp_enqueue_script( $handle );

		return true;
	}

	/**
	 * Enqueue a CSS file from a Composer package
	 *
	 * @param string      $handle       Style handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to CSS file from assets/
	 * @param array       $deps         Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. Media type. Default 'all'.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function enqueue_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		if ( ! self::register_style( $handle, $calling_file, $file, $deps, $ver, $media ) ) {
			return false;
		}

		wp_enqueue_style( $handle );

		return true;
	}

	/**
	 * Register a JavaScript file from a Composer package
	 *
	 * @param string      $handle       Script handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to JS file from assets/
	 * @param array       $deps         Optional. Script dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param array|bool  $args         Optional. Either a bool for $in_footer, or an array
	 *                                  accepting 'in_footer' (bool) and 'strategy'
	 *                                  ('defer'|'async'), matching wp_register_script()
	 *                                  since WordPress 6.3. Default true.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function register_script(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		$args = true
	): bool {
		$asset = self::resolve_asset( $calling_file, $file );
		if ( ! $asset ) {
			return false;
		}

		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		wp_register_script( $handle, $asset['file_url'], $deps, $version, self::normalize_script_args( $args ) );

		return true;
	}

	/**
	 * Normalise the script $args parameter
	 *
	 * WordPress 6.3 replaced wp_register_script()'s boolean $in_footer with an
	 * $args array that also carries a loading 'strategy' ('defer' or 'async').
	 * Passing a bool still works there, but a caller cannot request deferred
	 * loading through a boolean — so accept both shapes and hand WordPress the
	 * array form.
	 *
	 * An unrecognised strategy is dropped rather than forwarded: WordPress
	 * emits a _doing_it_wrong() notice for invalid values, and a typo in a
	 * caller is not worth a warning on every page load.
	 *
	 * @param array|bool $args Raw argument.
	 *
	 * @return array Normalised args array for WordPress.
	 */
	private static function normalize_script_args( $args ): array {
		if ( is_bool( $args ) ) {
			return [ 'in_footer' => $args ];
		}

		if ( ! is_array( $args ) ) {
			return [ 'in_footer' => true ];
		}

		$normalized = [ 'in_footer' => (bool) ( $args['in_footer'] ?? true ) ];

		if ( isset( $args['strategy'] ) && in_array( $args['strategy'], [ 'defer', 'async' ], true ) ) {
			$normalized['strategy'] = $args['strategy'];
		}

		return $normalized;
	}

	/**
	 * Register a CSS file from a Composer package
	 *
	 * @param string      $handle       Style handle for registration
	 * @param string      $calling_file File path to resolve assets relative to
	 * @param string      $file         Relative path to CSS file from assets/
	 * @param array       $deps         Optional. Style dependencies. Default empty array.
	 * @param string|bool $ver          Optional. Version string or false for auto-detection. Default false.
	 * @param string      $media        Optional. Media type. Default 'all'.
	 *
	 * @return bool True on success, false on failure
	 */
	public static function register_style(
		string $handle,
		string $calling_file,
		string $file,
		array $deps = [],
		$ver = false,
		string $media = 'all'
	): bool {
		$asset = self::resolve_asset( $calling_file, $file );
		if ( ! $asset ) {
			return false;
		}

		$version = ( $ver === false ) ? self::get_version( $asset['file_path'] ) : $ver;

		wp_register_style( $handle, $asset['file_url'], $deps, $version, $media );

		return true;
	}

	/**
	 * Get file contents from a Composer package
	 *
	 * Generic file loader for any asset type (SVG, JSON, XML, etc).
	 *
	 * Files come from inside a Composer package — shipped by the developer,
	 * not supplied by a user — so the contents are as trusted as the rest of
	 * the package's code. There is deliberately no sanitisation step here: see
	 * the note on the removal of $sanitize_svg in the 2.2.0 changelog. For
	 * untrusted SVG, use a real DOM-based sanitiser such as
	 * enshrined/svg-sanitize rather than anything regex-based.
	 *
	 * @param string $calling_file File path to resolve assets relative to
	 * @param string $file         Relative path to file from assets/ directory
	 *
	 * @return string|false File content or false on failure
	 */
	public static function get_file( string $calling_file, string $file ) {
		$asset = self::resolve_asset( $calling_file, $file );

		if ( ! $asset || ! isset( $asset['file_path'] ) ) {
			return false;
		}

		return file_get_contents( $asset['file_path'] );
	}

	/**
	 * Resolve asset paths from calling file
	 *
	 * @param string $calling_file The file to resolve assets relative to
	 * @param string $file         Relative file path from the assets directory
	 *
	 * @return array|null Complete asset information or null if not found
	 */
	public static function resolve_asset( string $calling_file, string $file ): ?array {
		$assets = self::locate_assets( $calling_file );
		if ( ! $assets ) {
			return null;
		}

		$file_paths = self::build_file_paths( $assets, $file );

		if ( ! file_exists( $file_paths['file_path'] ) ) {
			return null;
		}

		return array_merge( $assets, $file_paths );
	}

	/**
	 * Find assets directory relative to a calling file
	 *
	 * Uses caching to avoid repeated filesystem operations and automatically
	 * detects common Composer library directory structures.
	 *
	 * @param string $calling_file The file making the call
	 *
	 * @return array|null Array with 'path' and 'url' keys, or null if not found
	 */
	public static function locate_assets( string $calling_file ): ?array {
		$cache_key = $calling_file;

		if ( array_key_exists( $cache_key, self::$path_cache ) ) {
			return null === self::$path_cache[ $cache_key ]
				? null
				: [
					'path' => self::$path_cache[ $cache_key ],
					'url'  => self::$url_cache[ $cache_key ],
				];
		}

		$result = self::resolve_assets_dir( $calling_file );

		// Cache misses too. Without this, a package that legitimately has no
		// assets directory repeats the filesystem walk on every call.
		self::$path_cache[ $cache_key ] = $result['path'] ?? null;
		self::$url_cache[ $cache_key ]  = $result['url'] ?? null;

		return $result;
	}

	/**
	 * Locate the assets directory belonging to the calling file's package
	 *
	 * @param string $calling_file The file making the call
	 *
	 * @return array|null Array with 'path' and 'url' keys, or null if not found
	 */
	private static function resolve_assets_dir( string $calling_file ): ?array {
		$root = self::locate_package_root( $calling_file );

		if ( null === $root ) {
			return null;
		}

		$assets_path = $root . '/assets';

		if ( ! is_dir( $assets_path ) ) {
			return null;
		}

		$url = self::path_to_url( $assets_path );

		if ( null === $url ) {
			return null;
		}

		return [
			'path' => $assets_path,
			'url'  => $url,
		];
	}

	/**
	 * Find the root of the Composer package containing a file
	 *
	 * Walks up until it finds a composer.json, which by definition sits at a
	 * package's root — including in a Strauss-prefixed build, which copies it
	 * alongside the rewritten source.
	 *
	 * This replaces an earlier approach that tried a fixed list of relative
	 * paths ('/assets', '/../assets', '/../../assets', ...) and took the first
	 * directory named "assets" it found. That could walk straight out of the
	 * package: a package laid out as <package>/src/File.php with no assets
	 * directory of its own would climb past vendor/ and match the *host
	 * plugin's* assets directory, then serve every URL from there and cache the
	 * result. Whether it misfired depended entirely on what happened to exist
	 * in the surrounding tree.
	 *
	 * @param string $calling_file The file making the call
	 *
	 * @return string|null Absolute package root, or null if none was found
	 */
	private static function locate_package_root( string $calling_file ): ?string {
		$dir = dirname( $calling_file );

		for ( $depth = 0; $depth < self::MAX_DEPTH; $depth++ ) {
			if ( is_file( $dir . '/composer.json' ) ) {
				return $dir;
			}

			$parent = dirname( $dir );

			// dirname() is its own fixed point at the filesystem root.
			if ( $parent === $dir ) {
				break;
			}

			$dir = $parent;
		}

		return null;
	}

	/**
	 * Build file paths from base assets and relative file path
	 *
	 * @param array  $assets Base assets array with 'path' and 'url' keys
	 * @param string $file   Relative file path
	 *
	 * @return array Array with 'file_path' and 'file_url' keys
	 */
	private static function build_file_paths( array $assets, string $file ): array {
		return [
			'file_path' => $assets['path'] . '/' . ltrim( $file, '/' ),
			'file_url'  => $assets['url'] . '/' . ltrim( $file, '/' ),
		];
	}

	/**
	 * Convert filesystem path to URL
	 *
	 * @param string $path Absolute filesystem path
	 *
	 * @return string|null URL or null if conversion fails
	 */
	private static function path_to_url( string $path ): ?string {
		$path        = wp_normalize_path( $path );
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$content_url = content_url();

		// Check if path is within content directory
		if ( str_starts_with( $path, $content_dir ) ) {
			return str_replace( $content_dir, $content_url, $path );
		}

		// Check if path is within ABSPATH
		$abspath = wp_normalize_path( ABSPATH );
		if ( str_starts_with( $path, $abspath ) ) {
			return str_replace( $abspath, site_url( '/' ), $path );
		}

		return null;
	}

	/**
	 * Get file version based on modification time
	 *
	 * @param string $file_path Absolute path to file
	 *
	 * @return string Version string (timestamp)
	 */
	private static function get_version( string $file_path ): string {
		if ( file_exists( $file_path ) ) {
			return (string) filemtime( $file_path );
		}

		return '1.0.0';
	}


	/**
	 * Clear all caches
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$path_cache = [];
		self::$url_cache  = [];
	}
}
