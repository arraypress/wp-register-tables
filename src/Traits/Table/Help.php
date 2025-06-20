<?php
/**
 * Table Render Trait
 *
 * Provides rendering functionality for the Tables class.
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\Table;

// Exit if accessed directly
use ArrayPress\CustomTables\ListTable;
use WP_Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Help
 *
 * Rendering functionality for the Tables class help tabs
 */
trait Help {

	/**
	 * Setup help tabs for the screen
	 *
	 * @param WP_Screen $screen       Current screen
	 * @param array     $table_config Table configuration
	 */
	private function setup_help_tabs( WP_Screen $screen, array $table_config ): void {
		// Get help tabs configuration - support both new and legacy formats
		$help_tabs = [];
		if ( ! empty( $table_config['help']['tabs'] ) ) {
			$help_tabs = $table_config['help']['tabs'];
		} elseif ( ! empty( $table_config['help_tabs'] ) ) {
			$help_tabs = $table_config['help_tabs'];
		}

		if ( empty( $help_tabs ) || ! is_array( $help_tabs ) ) {
			return;
		}

		foreach ( $help_tabs as $tab_id => $tab ) {
			$screen->add_help_tab( [
				'id'       => $tab_id,
				'title'    => $tab['title'] ?? '',
				'content'  => $tab['content'] ?? '',
				'callback' => $tab['callback'] ?? null,
			] );
		}
	}

	/**
	 * Setup help sidebar for the screen
	 *
	 * @param WP_Screen $screen       Current screen
	 * @param array     $table_config Table configuration
	 */
	private function setup_help_sidebar( WP_Screen $screen, array $table_config ): void {
		$sidebar_content = '';

		// Check for direct sidebar HTML content
		if ( ! empty( $table_config['help']['sidebar'] ) ) {
			$sidebar_content = $table_config['help']['sidebar'];
		} elseif ( ! empty( $table_config['help_sidebar'] ) ) {
			$sidebar_content = $table_config['help_sidebar'];
		} // Check for links array in new format
		elseif ( ! empty( $table_config['help']['links'] ) ) {
			$sidebar_content = $this->build_sidebar_from_links( $table_config['help']['links'], true );
		} // Check for legacy sidebar links
		elseif ( ! empty( $table_config['help_sidebar_links'] ) && is_array( $table_config['help_sidebar_links'] ) ) {
			$sidebar_content = $this->build_sidebar_from_links( $table_config['help_sidebar_links'] );
		}

		// Set help sidebar if we have content
		if ( ! empty( $sidebar_content ) ) {
			$screen->set_help_sidebar( $sidebar_content );
		}
	}

	/**
	 * Build sidebar content from links array
	 *
	 * @param array $links      Array of link data
	 * @param bool  $simple_fmt Whether links use simple [text, url] format
	 *
	 * @return string HTML content for the sidebar
	 */
	private function build_sidebar_from_links( array $links, bool $simple_fmt = false ): string {
		$content = '<p><strong>' . __( 'For more information:', 'arraypress' ) . '</strong></p><ul>';

		foreach ( $links as $link ) {
			if ( $simple_fmt && is_array( $link ) && count( $link ) >= 2 ) {
				// Simple [text, url] format
				$content .= '<li><a href="' . esc_url( $link[1] ) . '">' . esc_html( $link[0] ) . '</a></li>';
			} elseif ( ! $simple_fmt && ! empty( $link['url'] ) && ! empty( $link['text'] ) ) {
				// Legacy format with 'url' and 'text' keys
				$content .= '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['text'] ) . '</a></li>';
			}
		}

		return $content . '</ul>';
	}

}