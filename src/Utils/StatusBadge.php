<?php
/**
 * Status Badge Utility
 *
 * Renders status badges with automatic styling based on status values.
 *
 * @package     ArrayPress\WP\RegisterTables
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Utils;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Class StatusBadge
 *
 * Handles rendering of status badges with automatic class mapping.
 *
 * @since 1.0.0
 */
class StatusBadge {

	/**
	 * Default status to class mapping
	 *
	 * @var array
	 */
	private static array $default_map = [
		// Success states
		'active'    => 'success',
		'completed' => 'success',
		'paid'      => 'success',
		'published' => 'success',
		'approved'  => 'success',
		'confirmed' => 'success',
		'delivered' => 'success',
		'verified'  => 'success',
		'valid'     => 'success',
		'enabled'   => 'success',
		'live'      => 'success',
		'open'      => 'success',

		// Warning states
		'pending'            => 'warning',
		'processing'         => 'warning',
		'draft'              => 'warning',
		'on-hold'            => 'warning',
		'on_hold'            => 'warning',
		'partially_refunded' => 'warning',
		'unpaid'             => 'warning',
		'expired'            => 'warning',
		'expiring'           => 'warning',
		'scheduled'          => 'warning',
		'awaiting'           => 'warning',
		'review'             => 'warning',
		'trial'              => 'warning',

		// Error states
		'failed'     => 'error',
		'cancelled'  => 'error',
		'canceled'   => 'error',
		'refunded'   => 'error',
		'rejected'   => 'error',
		'declined'   => 'error',
		'blocked'    => 'error',
		'revoked'    => 'error',
		'suspended'  => 'error',
		'terminated' => 'error',
		'error'      => 'error',
		'invalid'    => 'error',
		'spam'       => 'error',
		'closed'     => 'error',

		// Info states
		'new'     => 'info',
		'updated' => 'info',
		'info'    => 'info',
		'notice'  => 'info',

		// Default/neutral states
		'inactive' => 'default',
		'disabled' => 'default',
		'paused'   => 'default',
		'archived' => 'default',
		'hidden'   => 'default',
		'trashed'  => 'default',
		'unknown'  => 'default',
		'none'     => 'default',
	];

	/**
	 * Render a status badge
	 *
	 * @param string $status        Status value
	 * @param array  $custom_styles Custom status => class mappings
	 * @param array  $labels        Custom status => label mappings (or views config)
	 *
	 * @return string Badge HTML
	 */
	public static function render( string $status, array $custom_styles = [], array $labels = [] ): string {
		$class = self::get_class( $status, $custom_styles );
		$label = self::get_label( $status, $labels );

		return sprintf(
			'<span class="badge badge-%s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	/**
	 * Get the CSS class for a status
	 *
	 * @param string $status        Status value
	 * @param array  $custom_styles Custom status => class mappings
	 *
	 * @return string CSS class (success, warning, error, info, default)
	 */
	public static function get_class( string $status, array $custom_styles = [] ): string {
		// Check custom styles first
		if ( isset( $custom_styles[ $status ] ) ) {
			return $custom_styles[ $status ];
		}

		// Check default map
		$status_lower = strtolower( $status );
		if ( isset( self::$default_map[ $status_lower ] ) ) {
			return self::$default_map[ $status_lower ];
		}

		return 'default';
	}

	/**
	 * Get the display label for a status
	 *
	 * @param string $status Status value
	 * @param array  $labels Custom labels (can be views config array)
	 *
	 * @return string Display label
	 */
	public static function get_label( string $status, array $labels = [] ): string {
		// Check custom labels
		if ( isset( $labels[ $status ] ) ) {
			$label = $labels[ $status ];

			// Handle views config format
			if ( is_string( $label ) ) {
				return $label;
			} elseif ( is_array( $label ) && isset( $label['label'] ) ) {
				return $label['label'];
			}
		}

		// Convert status to readable label
		return ucwords( str_replace( [ '-', '_' ], ' ', $status ) );
	}

	/**
	 * Get the default status map
	 *
	 * @return array Status to class mapping
	 */
	public static function get_default_map(): array {
		return self::$default_map;
	}

	/**
	 * Check if a status maps to a specific class type
	 *
	 * @param string $status Status value
	 * @param string $class  Class type to check (success, warning, error, info, default)
	 *
	 * @return bool
	 */
	public static function is_class( string $status, string $class ): bool {
		return self::get_class( $status ) === $class;
	}

	/**
	 * Check if status indicates success
	 *
	 * @param string $status Status value
	 *
	 * @return bool
	 */
	public static function is_success( string $status ): bool {
		return self::is_class( $status, 'success' );
	}

	/**
	 * Check if status indicates warning
	 *
	 * @param string $status Status value
	 *
	 * @return bool
	 */
	public static function is_warning( string $status ): bool {
		return self::is_class( $status, 'warning' );
	}

	/**
	 * Check if status indicates error
	 *
	 * @param string $status Status value
	 *
	 * @return bool
	 */
	public static function is_error( string $status ): bool {
		return self::is_class( $status, 'error' );
	}

}