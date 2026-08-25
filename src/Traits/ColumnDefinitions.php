<?php
/**
 * Column Definitions
 *
 * @package     ArrayPress\RegisterTables
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL2+
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Traits;

/**
 * Which columns exist, which are hidden, and which can be sorted by.
 *
 * The answers core asks for before a single row is drawn. Hidden columns come
 * from the user's own screen options, so this is where a per-user preference
 * meets a per-table configuration — and the primary column has to be decided
 * here too, because core hangs the row actions off it and silently picks the
 * first one if nobody says otherwise.
 */
trait ColumnDefinitions {

    /**
     * Get column definitions
     *
     * Returns array of column key => label pairs for the table header.
     * Automatically adds checkbox column if bulk actions are configured.
     *
     * @return array Column definitions where keys are column identifiers
     *               and values are display labels.
     * @since 1.0.0
     *
     */
    public function get_columns(): array {
        $columns = [];

        // Add checkbox column if bulk actions exist
        if ( ! empty( $this->config['bulk_actions'] ) ) {
            $columns['cb'] = '<input type="checkbox" />';
        }

        // Build columns from config
        foreach ( $this->config['columns'] as $key => $column ) {
            if ( is_string( $column ) ) {
                $columns[ $key ] = $column;
            } elseif ( is_array( $column ) && isset( $column['label'] ) ) {
                $columns[ $key ] = $column['label'];
            }
        }

        /**
         * Filter the table columns
         *
         * @param array  $columns Column definitions.
         * @param array  $config  Table configuration.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( "arraypress_table_columns_{$this->id}", $columns, $this->config );
    }

    /**
     * Get hidden columns
     *
     * Returns array of column keys that should be hidden by default.
     * Users can show/hide columns via Screen Options.
     *
     * @return array Column keys to hide by default.
     * @since 1.0.0
     *
     */
    public function get_hidden_columns(): array {
        // Check user's saved column preferences first
        $screen = get_current_screen();
        if ( $screen ) {
            $hidden = get_user_option( 'manage' . $screen->id . 'columnshidden' );
            if ( is_array( $hidden ) ) {
                return $hidden;
            }
        }

        // Fall back to config defaults
        $hidden = $this->config['hidden_columns'] ?? [];

        /**
         * Filter the hidden columns
         *
         * @param array  $hidden Hidden column keys.
         * @param array  $config Table configuration.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( "arraypress_table_hidden_columns_{$this->id}", $hidden, $this->config );
    }

    /**
     * Get sortable columns
     *
     * Returns array defining which columns are sortable and their
     * default sort direction. Supports both simple array format
     * and explicit key => [orderby, desc_default] format.
     *
     * @return array Sortable column definitions where keys are column names
     *               and values are [orderby, is_descending_default] arrays.
     * @since 1.0.0
     *
     */
    public function get_sortable_columns(): array {
        $sortable = [];

        foreach ( $this->config['sortable'] as $key => $sort ) {
            if ( is_numeric( $key ) ) {
                // Simple format: ['column1', 'column2']
                $sortable[ $sort ] = [ $sort, false ];
            } else {
                // Advanced format: ['column' => ['orderby', true]]
                $sortable[ $key ] = $sort;
            }
        }

        /**
         * Filter the sortable columns
         *
         * @param array  $sortable Sortable column definitions.
         * @param array  $config   Table configuration.
         *
         * @since 1.0.0
         *
         */
        return apply_filters( "arraypress_table_sortable_columns_{$this->id}", $sortable, $this->config );
    }

    /**
     * Get primary column name
     *
     * Returns the column that should display row actions.
     * Falls back to parent method if not configured.
     *
     * @return string Primary column identifier.
     * @since 1.0.0
     *
     */
    protected function get_primary_column_name(): string {
        return $this->config['primary_column'] ?? parent::get_primary_column_name();
    }
}
