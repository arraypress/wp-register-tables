<?php
/**
 * Status Badge Library
 *
 * A lightweight utility for rendering styled status badges in WordPress.
 * Works in both admin and frontend contexts with sensible defaults for
 * common status values.
 *
 * @package     ArrayPress\WP\StatusBadge
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 * @author      David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\StatusBadge;

/**
 * Class StatusBadge
 *
 * Renders status badges with automatic styling based on status values.
 *
 * Usage:
 *
 *   // All defaults — covers most common statuses
 *   $badge = new StatusBadge();
 *   echo $badge->render( 'active' );
 *
 *   // With custom mappings merged over defaults
 *   $badge = new StatusBadge( [
 *       'churned'  => 'danger',
 *       'trialing' => 'warning',
 *   ] );
 *   echo $badge->render( 'churned' );
 *
 *   // One-off type override
 *   echo $badge->render( 'something', 'info' );
 *
 *   // Custom label
 *   echo $badge->render( 'in_progress', label: 'In Progress' );
 *
 * Badge types: success, warning, danger, info, default
 *
 * @since 1.0.0
 */
class StatusBadge {

	/**
	 * Badge type constants
	 */
	const SUCCESS = 'success';
	const WARNING = 'warning';
	const DANGER = 'danger';
	const INFO = 'info';
	const DEFAULT = 'default';

	/**
	 * Default status-to-type mapping
	 *
	 * Covers common status strings across WordPress plugins, e-commerce,
	 * CRM, and general application patterns.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULTS = [
		// Success (green)
		'active'             => self::SUCCESS,
		'approved'           => self::SUCCESS,
		'completed'          => self::SUCCESS,
		'confirmed'          => self::SUCCESS,
		'connected'          => self::SUCCESS,
		'delivered'          => self::SUCCESS,
		'enabled'            => self::SUCCESS,
		'live'               => self::SUCCESS,
		'open'               => self::SUCCESS,
		'paid'               => self::SUCCESS,
		'published'          => self::SUCCESS,
		'resolved'           => self::SUCCESS,
		'valid'              => self::SUCCESS,
		'verified'           => self::SUCCESS,
		'yes'                => self::SUCCESS,

		// Warning (amber)
		'awaiting'           => self::WARNING,
		'draft'              => self::WARNING,
		'expiring'           => self::WARNING,
		'on-hold'            => self::WARNING,
		'on_hold'            => self::WARNING,
		'partially_refunded' => self::WARNING,
		'pending'            => self::WARNING,
		'processing'         => self::WARNING,
		'review'             => self::WARNING,
		'scheduled'          => self::WARNING,
		'trial'              => self::WARNING,
		'trialing'           => self::WARNING,
		'unpaid'             => self::WARNING,

		// Danger (red)
		'banned'             => self::DANGER,
		'blocked'            => self::DANGER,
		'cancelled'          => self::DANGER,
		'canceled'           => self::DANGER,
		'declined'           => self::DANGER,
		'error'              => self::DANGER,
		'expired'            => self::DANGER,
		'failed'             => self::DANGER,
		'invalid'            => self::DANGER,
		'overdue'            => self::DANGER,
		'refunded'           => self::DANGER,
		'rejected'           => self::DANGER,
		'revoked'            => self::DANGER,
		'spam'               => self::DANGER,
		'suspended'          => self::DANGER,
		'terminated'         => self::DANGER,

		// Info (blue)
		'importing'          => self::INFO,
		'info'               => self::INFO,
		'new'                => self::INFO,
		'notice'             => self::INFO,
		'syncing'            => self::INFO,
		'updated'            => self::INFO,

		// Default (grey)
		'archived'           => self::DEFAULT,
		'closed'             => self::DEFAULT,
		'disabled'           => self::DEFAULT,
		'hidden'             => self::DEFAULT,
		'inactive'           => self::DEFAULT,
		'no'                 => self::DEFAULT,
		'none'               => self::DEFAULT,
		'paused'             => self::DEFAULT,
		'trashed'            => self::DEFAULT,
		'unknown'            => self::DEFAULT,
	];

	/**
	 * Icon mapping for each badge type
	 *
	 * @var array<string, string>
	 */
	private const ICONS = [
		self::SUCCESS => 'dashicons-yes-alt',
		self::WARNING => 'dashicons-clock',
		self::DANGER  => 'dashicons-dismiss',
		self::INFO    => 'dashicons-info-outline',
		self::DEFAULT => 'dashicons-marker',
	];

	/**
	 * Merged status-to-type map (defaults + custom)
	 *
	 * @var array<string, string>
	 */
	private array $map;

	/**
	 * Whether the stylesheet has been registered
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Constructor
	 *
	 * @param array<string, string> $custom  Optional custom status-to-type mappings.
	 *                                       Merged over built-in defaults.
	 */
	public function __construct( array $custom = [] ) {
		$this->map = array_merge( self::DEFAULTS, $custom );
		$this->register();
	}

	/**
	 * Render a status badge
	 *
	 * @param string      $status The status value (e.g. 'active', 'pending').
	 * @param string|null $type   Optional type override (success, warning, danger, info, default).
	 * @param string|null $label  Optional display label. Auto-generated from status if omitted.
	 *
	 * @return string Badge HTML.
	 */
	public function render( string $status, ?string $type = null, ?string $label = null ): string {
		$this->enqueue();

		$type  = $type ?? $this->get_type( $status );
		$label = $label ?? self::format_label( $status );
		$icon  = self::ICONS[ $type ] ?? self::ICONS[ self::DEFAULT ];

		return sprintf(
			'<span class="wp-status-badge wp-status-badge--%s"><span class="dashicons %s"></span>%s</span>',
			esc_attr( $type ),
			esc_attr( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * Enqueue the badge stylesheet
	 *
	 * Called automatically on first render(). Can also be called manually
	 * to enqueue the stylesheet early without rendering a badge.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		wp_enqueue_style( 'wp-status-badge' );
	}

	/**
	 * Get the badge type for a status
	 *
	 * @param string $status Status value.
	 *
	 * @return string Badge type (success, warning, danger, info, default).
	 */
	public function get_type( string $status ): string {
		$normalized = strtolower( trim( $status ) );

		return $this->map[ $normalized ] ?? self::DEFAULT;
	}

	/**
	 * Get the icon class for a badge type
	 *
	 * @param string $type Badge type.
	 *
	 * @return string Dashicon class.
	 */
	public function get_icon( string $type ): string {
		return self::ICONS[ $type ] ?? self::ICONS[ self::DEFAULT ];
	}

	/**
	 * Check if a status maps to a specific type
	 *
	 * @param string $status Status value.
	 * @param string $type   Type to check against.
	 *
	 * @return bool
	 */
	public function is_type( string $status, string $type ): bool {
		return $this->get_type( $status ) === $type;
	}

	/**
	 * Check if status indicates success
	 *
	 * @param string $status Status value.
	 *
	 * @return bool
	 */
	public function is_success( string $status ): bool {
		return $this->is_type( $status, self::SUCCESS );
	}

	/**
	 * Check if status indicates warning
	 *
	 * @param string $status Status value.
	 *
	 * @return bool
	 */
	public function is_warning( string $status ): bool {
		return $this->is_type( $status, self::WARNING );
	}

	/**
	 * Check if status indicates danger
	 *
	 * @param string $status Status value.
	 *
	 * @return bool
	 */
	public function is_danger( string $status ): bool {
		return $this->is_type( $status, self::DANGER );
	}

	/**
	 * Check if status indicates info
	 *
	 * @param string $status Status value.
	 *
	 * @return bool
	 */
	public function is_info( string $status ): bool {
		return $this->is_type( $status, self::INFO );
	}

	/**
	 * Get the full status-to-type map
	 *
	 * @return array<string, string>
	 */
	public function get_map(): array {
		return $this->map;
	}

	/**
	 * Get all valid badge types
	 *
	 * @return string[]
	 */
	public static function get_types(): array {
		return [ self::SUCCESS, self::WARNING, self::DANGER, self::INFO, self::DEFAULT ];
	}

	/**
	 * Convert a status string to a human-readable label
	 *
	 * Converts underscores and hyphens to spaces and capitalises each word.
	 *
	 * @param string $status Status value.
	 *
	 * @return string Formatted label.
	 */
	public static function format_label( string $status ): string {
		return ucwords( str_replace( [ '_', '-' ], ' ', $status ) );
	}

	/**
	 * Register the stylesheet via wp-composer-assets
	 *
	 * Called once per request regardless of how many instances are created.
	 *
	 * @return void
	 */
	private function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		wp_register_composer_style(
			'wp-status-badge',
			__FILE__,
			'css/status-badge.css',
			[ 'dashicons' ]
		);
	}

}