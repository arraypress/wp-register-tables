<?php
/**
 * WordPress stubs for the test suite.
 *
 * This library is a list-table builder rather than a field renderer, so it
 * needs a different and much smaller set than the field libraries do:
 * escaping, dates, and the handful of lookups a formatted column makes.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// wp-composer-assets resolves a library's URL from these, and does it at
// the moment an asset is first asked for rather than on load.
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', dirname( __DIR__, 3 ) );
}

if ( ! defined( 'WP_CONTENT_URL' ) ) {
	define( 'WP_CONTENT_URL', 'https://example.test/wp-content' );
}

/**
 * Reset every stubbed global between tests.
 *
 * @return void
 */
function rt_reset_globals(): void {
	$GLOBALS['rt_options'] = [
		'date_format' => 'F j, Y',
		'time_format' => 'g:i a',
		'timezone_string' => 'UTC',
		'gmt_offset' => 0,
	];
	$GLOBALS['rt_users']   = [];
	$GLOBALS['rt_terms']   = [];
}

rt_reset_globals();

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $context = 'display' ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class, $fallback = '' ) {
		$class = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $class );

		return '' === $class ? $fallback : $class;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default_value = false ) {
		return $GLOBALS['rt_options'][ $option ] ?? $default_value;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		return $GLOBALS['rt_users'][ $user_id ] ?? false;
	}
}

if ( ! function_exists( 'get_edit_user_link' ) ) {
	function get_edit_user_link( $user_id = null ) {
		return 'https://example.test/wp-admin/user-edit.php?user_id=' . (int) $user_id;
	}
}

if ( ! function_exists( 'get_avatar' ) ) {
	function get_avatar( $id_or_email, $size = 96, $default = '', $alt = '' ) {
		return '<img class="avatar" alt="" />';
	}
}

if ( ! function_exists( 'mysql2date' ) ) {
	function mysql2date( $format, $date, $translate = true ) {
		$time = strtotime( (string) $date );

		return false === $time ? false : gmdate( (string) $format, $time );
	}
}

if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( $from, $to = 0 ) {
		return 'a while';
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return 'timestamp' === $type ? 1000000000 : gmdate( 'Y-m-d H:i:s', 1000000000 );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		return gmdate( (string) $format, $timestamp ?? 1000000000 );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'https://example.test/wp-content/plugins/x/';
	}
}

if ( ! function_exists( 'content_url' ) ) {
	function content_url( $path = '' ) {
		return WP_CONTENT_URL . '/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '', $scheme = null ) {
		return 'https://example.test/' . ltrim( (string) $path, '/' );
	}
}

/*
 * The status badge registers its own stylesheet the first time one is
 * rendered, which reaches wp-composer-assets and from there the whole asset
 * API. Stubbed rather than avoided, because "render a status" is exactly
 * what the test is for.
 */
foreach ( [ 'wp_register_style', 'wp_enqueue_style', 'wp_register_script', 'wp_enqueue_script', 'wp_add_inline_style', 'wp_add_inline_script' ] as $rt_asset_fn ) {
	if ( ! function_exists( $rt_asset_fn ) ) {
		eval( "function {$rt_asset_fn}() { return true; }" );
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( $handle, $status = 'enqueued' ) {
		return false;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook ) {
		return 1;
	}
}
