<?php
/**
 * Table Instance Filter Fields Trait
 *
 * @package     ArrayPress\WP\Register\Traits
 * @copyright   Copyright (c) 2025, ArrayPress Limited
 * @license     GPL2+
 * @version     1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\CustomTables\Traits\ListTable;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

use ArrayPress\CustomTables\ListTable;
use ArrayPress\CustomTables\Utils;
use Elementify\Create;

/**
 * FilterFields Trait
 *
 * Provides filter field rendering functionality for table instances.
 */
trait FilterFields {

	/**
	 * Get HTML for a filter input
	 *
	 * @param string $filter_id     Filter identifier
	 * @param array  $filter        Filter configuration
	 * @param mixed  $current_value Current filter value
	 *
	 * @return string               Filter HTML
	 */
	protected function get_filter_input_html( string $filter_id, array $filter, $current_value ): string {
		$type = $filter['type'] ?? 'text';

		// Use specialized method if available
		$method_name = "render_{$type}_filter";
		if ( method_exists( $this, $method_name ) ) {
			return $this->$method_name( $filter_id, $filter, $current_value );
		}

		/**
		 * Filters the custom filter input HTML.
		 *
		 * Allows plugins to provide custom HTML for filter inputs.
		 *
		 * @param string    $html          The custom filter HTML (empty by default)
		 * @param string    $filter_id     The filter ID
		 * @param array     $filter        The filter configuration
		 * @param mixed     $current_value The current filter value
		 * @param ListTable $this          The table instance
		 *
		 * @return string                   Custom filter HTML
		 */
		$custom_input = apply_filters(
			"{$this->hook_prefix}custom_filter_input",
			'',
			$filter_id,
			$filter,
			$current_value,
			$this
		);

		if ( ! empty( $custom_input ) ) {
			return $custom_input;
		}

		// Default to text input
		return $this->render_text_filter( $filter_id, $filter, $current_value );
	}

	/**
	 * Render a date range filter
	 *
	 * @param string $filter_id Filter identifier
	 * @param array  $filter    Filter configuration
	 */
	protected function render_date_range_filter( string $filter_id, array $filter ): void {
		$from_param = "{$filter_id}_from";
		$to_param   = "{$filter_id}_to";
		$from_value = $this->get_request_var( $from_param, '' );
		$to_value   = $this->get_request_var( $to_param, '' );

		// Create date range picker
		echo Create::div(
			Create::datepicker_range(
				$from_param,
				$to_param,
				$from_value,
				$to_value,
				[
					'start_placeholder' => __( 'From', 'arraypress' ),
					'end_placeholder'   => __( 'To', 'arraypress' )
				]
			),
			[ 'class' => 'wp-filter-field wp-filter-date-range' ]
		)->render();
	}

	/**
	 * Get common attributes for a filter input
	 *
	 * @param string $filter_id Filter identifier
	 * @param array  $filter    Filter configuration
	 * @param array  $defaults  Additional default attributes
	 *
	 * @return array            Complete attributes array
	 */
	protected function prepare_input_attributes( string $filter_id, array $filter, array $defaults = [] ): array {
		$attributes = array_merge( [
			'id'          => "filter-{$filter_id}",
			'class'       => $filter['class'] ?? '',
			'placeholder' => $filter['placeholder'] ?? '',
		], $defaults );

		// Add any custom attributes from filter config
		if ( isset( $filter['attributes'] ) && is_array( $filter['attributes'] ) ) {
			$attributes = array_merge( $attributes, $filter['attributes'] );
		}

		return $attributes;
	}

	/**
	 * Render a select filter
	 *
	 * @param string $filter_id     Filter identifier
	 * @param array  $filter        Filter configuration
	 * @param mixed  $current_value Current filter value
	 *
	 * @return string               Filter HTML
	 */
	protected function render_select_filter( string $filter_id, array $filter, $current_value ): string {
		$placeholder    = $filter['placeholder'] ?? '';
		$options        = Utils::get_processed_options( $filter['options'] ?? [] );
		$select_options = [ '' => $placeholder ] + $options;
		$attributes     = $this->prepare_input_attributes( $filter_id, $filter );

		return Create::select(
			$filter_id,
			$select_options,
			$current_value,
			$attributes
		)->render();
	}

	/**
	 * Render a text filter
	 *
	 * @param string $filter_id     Filter identifier
	 * @param array  $filter        Filter configuration
	 * @param mixed  $current_value Current filter value
	 *
	 * @return string               Filter HTML
	 */
	protected function render_text_filter( string $filter_id, array $filter, $current_value ): string {
		$attributes = $this->prepare_input_attributes( $filter_id, $filter );

		return Create::text(
			$filter_id,
			$current_value,
			$attributes
		)->render();
	}

	/**
	 * Render a number filter
	 *
	 * @param string $filter_id     Filter identifier
	 * @param array  $filter        Filter configuration
	 * @param mixed  $current_value Current filter value
	 *
	 * @return string               Filter HTML
	 */
	protected function render_number_filter( string $filter_id, array $filter, $current_value ): string {
		$attributes = $this->prepare_input_attributes( $filter_id, $filter, [
			'min'  => $filter['min'] ?? null,
			'max'  => $filter['max'] ?? null,
			'step' => $filter['step'] ?? null,
		] );

		return Create::number(
			$filter_id,
			$current_value,
			$attributes
		)->render();
	}

	/**
	 * Render a checkbox filter
	 *
	 * @param string $filter_id     Filter identifier
	 * @param array  $filter        Filter configuration
	 * @param mixed  $current_value Current filter value
	 *
	 * @return string               Filter HTML
	 */
	protected function render_checkbox_filter( string $filter_id, array $filter, $current_value ): string {
		$label      = $filter['label'] ?? $filter['placeholder'] ?? '';
		$attributes = $this->prepare_input_attributes( $filter_id, $filter );

		return Create::div(
			Create::label(
				'',
				Create::checkbox(
					$filter_id,
					'1',
					$current_value == '1',
					$attributes
				)->render() . ' ' . esc_html( $label )
			),
			[ 'class' => 'wp-checkbox-label' ]
		)->render();
	}

	/**
	 * Render a toggle filter
	 *
	 * @param string $filter_id     Filter identifier
	 * @param array  $filter        Filter configuration
	 * @param mixed  $current_value Current filter value
	 *
	 * @return string               Filter HTML
	 */
	protected function render_toggle_filter( string $filter_id, array $filter, $current_value ): string {
		$label      = $filter['label'] ?? $filter['placeholder'] ?? '';
		$checked    = ! empty( $current_value ) && $current_value !== '0';
		$value      = $filter['value'] ?? '1';
		$disabled   = isset( $filter['disabled'] ) && $filter['disabled'];
		$attributes = $this->prepare_input_attributes( $filter_id, $filter );

		// Use alternate style if specified
		if ( isset( $filter['alt_style'] ) && $filter['alt_style'] ) {
			$attributes['class'] = trim( $attributes['class'] . ' elementify-toggle-alt' );
		}

		return Create::toggle(
			$filter_id,
			$checked,
			$value,
			$label,
			$attributes,
			$disabled
		)->render();
	}

	/**
	 * Render a range filter
	 *
	 * @param string $filter_id     Filter identifier
	 * @param array  $filter        Filter configuration
	 * @param mixed  $current_value Current filter value
	 *
	 * @return string               Filter HTML
	 */
	protected function render_range_filter( string $filter_id, array $filter, $current_value ): string {
		// Set default value if none provided
		if ( empty( $current_value ) ) {
			$current_value = $filter['default'] ?? $filter['min'] ?? '0';
		}

		$attributes = [
			'class' => 'wp-filter-range ' . ( $filter['class'] ?? '' )
		];

		// Add custom attributes
		if ( isset( $filter['attributes'] ) && is_array( $filter['attributes'] ) ) {
			$attributes = array_merge( $attributes, $filter['attributes'] );
		}

		// Use Elementify's range component
		return Create::range(
			$filter_id,
			$current_value,
			$filter['min'] ?? '0',
			$filter['max'] ?? '100',
			$filter['step'] ?? '1',
			! isset( $filter['display_value'] ) || $filter['display_value'],
			$attributes
		)->render();
	}

}