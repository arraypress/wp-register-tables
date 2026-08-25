<?php
/**
 * Relational Column Formatters
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

use ArrayPress\Stripe\Format;
use WP_Term;

/**
 * Columns holding an id that stands for something else — a user, a customer,
 * a set of terms, a list of items.
 *
 * Each resolves the id to a name and links it where there is somewhere to
 * link to. The link is the point: an integer in a cell is a column nobody can
 * use, and looking it up by hand is what a list table exists to avoid.
 *
 * A row that no longer resolves prints what it has rather than a blank. A
 * deleted user is still a fact about the row.
 */
trait RelationFormatters {

	/**
	 * Format a user ID as an avatar with linked display name
	 *
	 * Renders a WordPress user's avatar image alongside their display name,
	 * linked to the user's profile edit screen.
	 *
	 * Avatar size is extracted from config['avatar'], defaulting to 32px.
	 *
	 * @param mixed  $value       User ID.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'avatar' (int) Avatar size in pixels (default: 32).
	 *
	 * @return string Formatted user HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_user( $value, $item, string $column_name, array $config = [] ): string {
		$avatar_size = $config['avatar'] ?? 32;
		$user_id     = (int) $value;

		if ( $user_id < 1 ) {
			return self::render_empty();
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return self::render_empty();
		}

		$name       = esc_html( $user->display_name );
		$avatar_img = get_avatar( $user_id, $avatar_size );
		$edit_url   = get_edit_user_link( $user_id );

		$name_html = $edit_url
			? sprintf( '<a href="%s">%s</a>', esc_url( $edit_url ), $name )
			: $name;

		$output = sprintf(
			'<span class="column-user">%s %s</span>',
			$avatar_img,
			$name_html
		);

		return $output;
	}

	/**
	 * Format a customer/person value with avatar, name, and email
	 *
	 * Inspects the data object for common getter methods to build
	 * a rich display with avatar, name, email, and optional link.
	 * Works with any object that exposes some combination of:
	 *
	 * - get_email() or get_customer_email()
	 * - get_name() or get_display_name() or get_customer_name()
	 * - get_url() or get_edit_url() or get_admin_url()
	 *
	 * Falls back gracefully when methods are missing. If the value
	 * is a numeric user ID, delegates to format_user() instead.
	 *
	 * @param mixed  $value       Object, user ID, or customer ID.
	 * @param object $item        The row data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'avatar'      (int)    Avatar size in pixels (default: 32).
	 *                            - '_filter_url' (string) Explicit URL override for name link.
	 *
	 * @return string Formatted customer HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_customer( $value, $item, string $column_name, array $config = [] ): string {
		$avatar_size = $config['avatar'] ?? 32;

		// If it's a numeric ID, try the item itself for customer methods
		$source = is_object( $value ) ? $value : $item;

		// Resolve email
		$email = self::resolve_method( $source, [
			'get_email',
			'get_customer_email',
		] );

		// Resolve name
		$name = self::resolve_method( $source, [
			'get_display_name',
			'get_name',
			'get_customer_name',
		] );

		// Resolve URL: explicit filter URL wins, then method resolution
		$url = $config['_filter_url'] ?? self::resolve_method( $source, [
			'get_edit_url',
			'get_admin_url',
			'get_admin_orders_url',
			'get_url',
		] );

		// If we have nothing to work with, fall back to user formatter
		if ( empty( $email ) && empty( $name ) ) {
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				return self::format_user( $value, $item, $column_name, $config );
			}

			return self::render_empty();
		}

		// Build avatar from email
		$avatar_html = '';
		if ( ! empty( $email ) ) {
			$avatar_html = get_avatar( $email, $avatar_size );
		}

		// Fall back name to email local part
		if ( empty( $name ) && ! empty( $email ) ) {
			$name = strstr( $email, '@', true );
		}

		// Build name HTML with optional link
		$name_escaped = esc_html( $name );
		$name_html    = ! empty( $url )
			? sprintf( '<a href="%s">%s</a>', esc_url( $url ), $name_escaped )
			: $name_escaped;

		// Build email subtitle
		$email_html = '';
		if ( ! empty( $email ) ) {
			$email_html = sprintf(
				'<span class="column-customer-email">%s</span>',
				esc_html( $email )
			);
		}

		return sprintf(
			'<span class="column-customer">%s<span class="column-customer-detail">%s%s</span></span>',
			$avatar_html,
			$name_html,
			$email_html
		);
	}

	/**
	 * Format taxonomy terms as linked badges
	 *
	 * Accepts an array of term names, term objects, or WP_Term objects.
	 * When config['taxonomy'] is provided, term names link to their
	 * admin edit screens.
	 *
	 * @param mixed  $value       Array of term names, term objects, or WP_Term objects.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'taxonomy' (string) Taxonomy slug for admin links.
	 *
	 * @return string Formatted terms HTML, or empty placeholder.
	 *
	 * @since 1.0.0
	 */
	public static function format_taxonomy( $value, $item, string $column_name, array $config = [] ): string {
		if ( ! is_array( $value ) ) {
			return self::render_empty();
		}

		$taxonomy = $config['taxonomy'] ?? null;
		$terms    = [];

		foreach ( $value as $term ) {
			$name    = null;
			$term_id = null;

			if ( $term instanceof WP_Term ) {
				$name    = $term->name;
				$term_id = $term->term_id;
			} elseif ( is_object( $term ) && isset( $term->name ) ) {
				$name    = $term->name;
				$term_id = $term->term_id ?? null;
			} elseif ( is_string( $term ) ) {
				$name = $term;
			}

			if ( empty( $name ) ) {
				continue;
			}

			// Link to term admin page if taxonomy is known and we have an ID
			if ( $taxonomy && $term_id ) {
				$edit_url = get_edit_term_link( $term_id, $taxonomy );

				if ( $edit_url ) {
					$terms[] = sprintf(
						'<a href="%s" class="column-term">%s</a>',
						esc_url( $edit_url ),
						esc_html( $name )
					);
					continue;
				}
			}

			$terms[] = sprintf( '<span class="column-term">%s</span>', esc_html( $name ) );
		}

		if ( empty( $terms ) ) {
			return self::render_empty();
		}

		return sprintf( '<span class="column-taxonomy">%s</span>', implode( ', ', $terms ) );
	}

	/**
	 * Format an items summary from an array of names or a numeric count
	 *
	 * Arrays render as:
	 * - 1 item:   "Item Name"
	 * - 2 items:  "Item Name and 1 other item"
	 * - 3+ items: "Item Name and 2 other items"
	 *
	 * Numeric values render as "4 items".
	 *
	 * Labels are extracted from config['singular'] and config['plural'],
	 * defaulting to "item" and "items" respectively.
	 *
	 * @param mixed  $value       Array of item names/objects or numeric count.
	 * @param object $item        Data object.
	 * @param string $column_name Column name.
	 * @param array  $config      Column configuration. Supports:
	 *                            - 'singular' (string) Singular label (default: "item").
	 *                            - 'plural'   (string) Plural label (default: "items").
	 *
	 * @return string Formatted items HTML.
	 *
	 * @since 1.0.0
	 */
	public static function format_items( $value, $item, string $column_name, array $config = [] ): string {
		$singular = $config['singular'] ?? __( 'item', 'arraypress' );
		$plural   = $config['plural'] ?? __( 'items', 'arraypress' );

		// Numeric count
		if ( is_numeric( $value ) ) {
			$count = (int) $value;

			if ( $count === 0 ) {
				return self::render_empty();
			}

			$output = sprintf(
				'<span class="column-items">%s %s</span>',
				esc_html( number_format_i18n( $count ) ),
				esc_html( $count === 1 ? $singular : $plural )
			);
		} elseif ( is_array( $value ) ) {
			$names = array_filter( array_map( function ( $entry ) {
				return is_object( $entry ) && isset( $entry->name )
					? $entry->name
					: (string) $entry;
			}, $value ) );

			$count = count( $names );

			if ( $count === 0 ) {
				return self::render_empty();
			}

			$first = esc_html( $names[0] );
			$rest  = $count - 1;

			if ( $rest === 0 ) {
				$output = sprintf( '<span class="column-items">%s</span>', $first );
			} else {
				$output = sprintf(
					'<span class="column-items">%s <span class="column-items-rest">%s %s %s</span></span>',
					$first,
					esc_html__( 'and', 'arraypress' ),
					esc_html( number_format_i18n( $rest ) ),
					esc_html( sprintf(
						/* translators: %s: what the remaining items are called, singular or plural */
						__( 'other %s', 'arraypress' ),
						1 === $rest ? $singular : $plural
					) )
				);
			}
		} else {
			return self::render_empty();
		}

		return $output;
	}
}
