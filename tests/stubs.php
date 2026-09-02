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

	// Everything allowed and every nonce good, so a test that is not about
	// security need not say so.
	$GLOBALS['rt_caps']     = null;
	$GLOBALS['rt_nonce_ok'] = true;

	unset(
		$GLOBALS['rt_screen'],
		$GLOBALS['rt_user_meta'],
		$GLOBALS['rt_redirect'],
		$GLOBALS['rt_fired'],
		$GLOBALS['rt_actions'],
		$GLOBALS['rt_filters']
	);
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

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( $color ) {
		if ( '' === $color ) {
			return '';
		}

		return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ? $color : null;
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
 *
 * Written out one at a time rather than generated in a loop. A loop needs the
 * runtime code interpreter to declare a function from a name, and this file
 * ships -- Composer installs tests/ alongside src/ unless a .gitattributes
 * says otherwise, and that construct is the first thing a WordPress security
 * scanner looks for in a plugin's vendor tree.
 */
if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	function wp_add_inline_script( ...$args ) {
		return true;
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

/*
 * Admin URLs, for the page-address tests.
 *
 * add_query_arg() is written out rather than approximated: the real one keeps
 * the argument order it was given and merges into an existing query string,
 * and both matter to what page_url() produces.
 */

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		if ( is_array( $args[0] ) ) {
			$pairs = $args[0];
			$url   = (string) ( $args[1] ?? '' );
		} else {
			$pairs = [ (string) $args[0] => $args[1] ];
			$url   = (string) ( $args[2] ?? '' );
		}

		$parts    = explode( '?', $url, 2 );
		$existing = [];

		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $existing );
		}

		$query = array_merge( $existing, $pairs );

		return '' === $query ? $parts[0] : $parts[0] . '?' . http_build_query( $query );
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $key, $url = '' ) {
		$parts = explode( '?', (string) $url, 2 );
		$query = [];

		if ( isset( $parts[1] ) ) {
			parse_str( $parts[1], $query );
		}

		foreach ( (array) $key as $one ) {
			unset( $query[ $one ] );
		}

		return [] === $query ? $parts[0] : $parts[0] . '?' . http_build_query( $query );
	}
}

/*
 * The screen, and the user meta the per-page option lives in. Null by default:
 * a table has a per-page long before anyone has opened Screen Options, and
 * that is the path worth having as the default in a test.
 */
if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen() {
		return $GLOBALS['rt_screen'] ?? null;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return $GLOBALS['rt_user_id'] ?? 1;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		return $GLOBALS['rt_user_meta'][ $key ] ?? '';
	}
}

/*
 * The rest of what the manager reaches for. Registration fills a table's
 * configuration with defaults, and testing a table built any other way would
 * be testing a shape the library never produces.
 */
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rt_actions'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['rt_filters'][ $hook ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['rt_filters'][ $hook ] ?? [] as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

/*
 * Hooks that actually hold what is hooked.
 *
 * These were defined twice in this file: an inert pair earlier that returned
 * true and dropped the callback, and this recording pair below. The earlier
 * definitions won the function_exists guard, so add_action stored nothing,
 * apply_filters ignored every filter, and do_action fired hooks nobody was
 * listening to. Any test asserting that a hook did something passed because
 * nothing could disagree.
 */
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		$GLOBALS['rt_fired'][] = $hook;

		foreach ( $GLOBALS['rt_actions'][ $hook ] ?? [] as $callback ) {
			$callback( ...$args );
		}
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( ...$args ) {
		$url = is_array( $args[0] ) ? ( $args[1] ?? '' ) : ( $args[2] ?? '' );
		$add = is_array( $args[0] ) ? $args[0] : [ $args[0] => $args[1] ];

		return $url . ( str_contains( (string) $url, '?' ) ? '&' : '?' ) . http_build_query( $add );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

/*
 * The security primitives, so an action can be tested as refused rather than
 * only as performed.
 *
 * $GLOBALS['rt_caps'] is what the current user may do — null for everything,
 * which is the default so most tests need not care. $GLOBALS['rt_nonce_ok']
 * is whether a nonce checks out.
 */
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		$allowed = $GLOBALS['rt_caps'] ?? null;

		return null === $allowed || in_array( $capability, (array) $allowed, true );
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return ( $GLOBALS['rt_nonce_ok'] ?? true ) ? 1 : false;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce';
	}
}

/**
 * Thrown in place of the redirect, so a test can see what happened after it.
 *
 * Every action in this library ends `wp_safe_redirect(); exit;`, which is
 * right in production and ends the PHP process in a test — PHPUnit reports it
 * as "premature end of PHP process" and the assertions never run. Throwing
 * from the redirect means the exit is never reached and the test regains
 * control, with the destination to inspect.
 */
final class RT_Redirected extends \RuntimeException {

	/**
	 * Where it was going.
	 *
	 * @var string
	 */
	public string $location;

	/**
	 * @param string $location The destination.
	 */
	public function __construct( string $location ) {
		parent::__construct( 'Redirected to ' . $location );

		$this->location = $location;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['rt_redirect'] = $location;

		throw new RT_Redirected( (string) $location );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new \RuntimeException( is_string( $message ) ? $message : 'wp_die' );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone(): DateTimeZone {
		return new DateTimeZone( $GLOBALS['rt_options']['timezone'] ?? 'UTC' );
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp = null ) {
		return gmdate( (string) $format, null === $timestamp ? time() : (int) $timestamp );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

/*
 * page_args() reads the query string back out of the URL page_url() built,
 * so the suite needs core's parser as well as its builder.
 */
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />';

		if ( $display ) {
			echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $field;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other = null ) {
		$button = '<input type="submit" name="' . esc_attr( $name ) . '" class="button button-' . esc_attr( (string) $type )
			. '" value="' . esc_attr( $text ) . '" />';

		if ( $wrap ) {
			$button = '<p class="submit">' . $button . '</p>';
		}

		echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	/**
	 * The nonce check, which stops the request when it fails.
	 *
	 * Real WordPress dies here, and a stub that returned false instead would
	 * let every test past the check continue into code the real thing never
	 * reaches -- which is how a broken nonce check looks like a passing suite.
	 */
	function check_ajax_referer( $action, $name = false, $die = true ) {
		$nonce = $_REQUEST[ $name ] ?? '';

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_die( -1, 403 );
		}

		return 1;
	}
}
