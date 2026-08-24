<?php
/**
 * Request Reading
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	return;
}

/**
 * The query arguments a list table is looking at.
 *
 * A list table's state is in the URL: which page, which search, which filter,
 * which column it is ordered by, which page number. All of it is a *view* —
 * it changes nothing, and core's own list tables read theirs exactly this way
 * — but every read of $_GET looks identical to phpcs whether it decides what
 * to show or what to delete, so each one asked for a nonce that a link cannot
 * carry.
 *
 * Answering that seventy times over, once per read, would put seventy
 * annotations in front of seventy near-identical lines and make the two reads
 * that *are* actions impossible to pick out. Reading through here instead
 * leaves the question answered in one place, and unslashing and sanitizing
 * done the same way every time rather than remembered.
 *
 * What does not belong here: anything that acts. A row action, a bulk action
 * and a delete each verify a nonce of their own before they read anything,
 * and they read it themselves so that the check and the read stay side by
 * side where both can be seen.
 */
final class Request {

	/**
	 * Whether a query argument is present.
	 *
	 * @param string $key Argument name.
	 *
	 * @return bool
	 */
	public static function has( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view, not an action. See the class comment.
		return isset( $_GET[ $key ] );
	}

	/**
	 * A query argument as text.
	 *
	 * @param string $key      Argument name.
	 * @param string $fallback Returned when absent.
	 *
	 * @return string
	 */
	public static function text( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view, not an action. See the class comment.
		return isset( $_GET[ $key ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) )
			: $fallback;
	}

	/**
	 * A query argument as a key.
	 *
	 * @param string $key      Argument name.
	 * @param string $fallback Returned when absent.
	 *
	 * @return string
	 */
	public static function key( string $key, string $fallback = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view, not an action. See the class comment.
		return isset( $_GET[ $key ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( (string) $_GET[ $key ] ) )
			: $fallback;
	}

	/**
	 * A query argument as a non-negative integer.
	 *
	 * @param string $key      Argument name.
	 * @param int    $fallback Returned when absent.
	 *
	 * @return int
	 */
	public static function count( string $key, int $fallback = 0 ): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view, not an action. See the class comment.
		return isset( $_GET[ $key ] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? absint( wp_unslash( $_GET[ $key ] ) )
			: $fallback;
	}

	/**
	 * Whether a query argument is present and not empty.
	 *
	 * @param string $key Argument name.
	 *
	 * @return bool
	 */
	public static function filled( string $key ): bool {
		return '' !== self::text( $key );
	}

	/**
	 * Every query argument, for handing to a consumer's callback.
	 *
	 * Deliberately raw and deliberately the only way to get it: a callback
	 * that wants the whole query string is responsible for what it does with
	 * it, and burying that behind a helper that looked sanitizing would be
	 * worse than saying so.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a view, not an action. See the class comment.
		return wp_unslash( $_GET );
	}
}
