<?php
/**
 * Row
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.2.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables;

/**
 * Reading a field off a row, whatever shape the row is.
 *
 * `get_items()` hands back whatever the plugin's query returned. That is an
 * object for anything with a model layer, and an array for everything else —
 * `$wpdb->get_results()` with `ARRAY_A`, a REST response decoded to arrays, a
 * plugin that never had objects.
 *
 * Three places used to assume an object and reach for `method_exists()` or
 * `property_exists()`, both of which take an object or a class name and throw
 * a TypeError on an array. So a table built the ordinary way died on its
 * first row with a stack trace where the list should be, and only tables
 * whose rows happened to be objects worked. Nothing documented the
 * restriction, because nothing intended it.
 */
final class Row {

	/**
	 * Read a field off a row.
	 *
	 * A getter first, because a model that has one usually means it: an
	 * `Order::get_total()` may add tax that `$order->total` does not.
	 *
	 * @param mixed  $item     The row.
	 * @param string $field    The field.
	 * @param mixed  $fallback Returned when the row has no such field.
	 *
	 * @return mixed
	 */
	public static function get( mixed $item, string $field, mixed $fallback = null ): mixed {
		if ( is_object( $item ) ) {
			$getter = 'get_' . $field;

			if ( method_exists( $item, $getter ) ) {
				return $item->$getter();
			}

			return isset( $item->$field ) || property_exists( $item, $field ) ? $item->$field : $fallback;
		}

		if ( is_array( $item ) ) {
			return array_key_exists( $field, $item ) ? $item[ $field ] : $fallback;
		}

		return $fallback;
	}

	/**
	 * Whether a row has a field at all.
	 *
	 * Told apart from a field that is there and empty, because a column whose
	 * value is nought should render nought rather than the placeholder for a
	 * column that does not apply.
	 *
	 * @param mixed  $item  The row.
	 * @param string $field The field.
	 *
	 * @return bool
	 */
	public static function has( mixed $item, string $field ): bool {
		if ( is_object( $item ) ) {
			return method_exists( $item, 'get_' . $field ) || property_exists( $item, $field );
		}

		return is_array( $item ) && array_key_exists( $field, $item );
	}

	/**
	 * A row's id.
	 *
	 * What the checkbox column and every bulk action need, and the one field
	 * a table cannot do without.
	 *
	 * @param mixed $item The row.
	 *
	 * @return int
	 */
	public static function id( mixed $item ): int {
		return (int) self::get( $item, 'id', 0 );
	}
}
