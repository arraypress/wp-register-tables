<?php
/**
 * A stand-in for core's WP_List_Table.
 *
 * `Table` requires the real one from ABSPATH, and ABSPATH is the tests
 * directory here — so this file sits exactly where that require looks and no
 * production code has to know a test is running.
 *
 * Only the members `Table` actually touches are here. Core's is fourteen
 * hundred lines of markup, and a copy of it would drift; what matters for a
 * test is that the subclass's own decisions — which columns, which views,
 * which query arguments — are reachable and correct.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

/**
 * The parts of core's list table this library builds on.
 */
class WP_List_Table {

	/**
	 * The rows.
	 *
	 * @var array<int, mixed>
	 */
	public $items = [];

	/**
	 * Columns, hidden columns, sortable columns, primary column.
	 *
	 * @var array<int, mixed>
	 */
	protected $_column_headers = [];

	/**
	 * What was passed to the constructor.
	 *
	 * @var array<string, mixed>
	 */
	protected $_args = [];

	/**
	 * What set_pagination_args() was given.
	 *
	 * @var array<string, mixed>
	 */
	protected $_pagination_args = [];

	/**
	 * The screen.
	 *
	 * @var object|null
	 */
	public $screen = null;

	/**
	 * Construct.
	 *
	 * @param array<string, mixed> $args Arguments.
	 */
	public function __construct( $args = [] ) {
		$this->_args = (array) $args;
	}

	/**
	 * Record the pagination.
	 *
	 * @param array<string, mixed> $args Pagination arguments.
	 *
	 * @return void
	 */
	public function set_pagination_args( $args ) {
		$this->_pagination_args = (array) $args;
	}

	/**
	 * What set_pagination_args() was given.
	 *
	 * Not core's API — core has `get_pagination_arg( $key )` — but a test
	 * needs the whole lot, and reaching into a protected property from
	 * outside is worse than one accessor that says what it is for.
	 *
	 * @return array<string, mixed>
	 */
	public function pagination_args() {
		return $this->_pagination_args;
	}

	/**
	 * What the constructor was given.
	 *
	 * @return array<string, mixed>
	 */
	public function args() {
		return $this->_args;
	}

	/**
	 * The page being viewed.
	 *
	 * @return int
	 */
	public function get_pagenum() {
		$page = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;

		return max( 1, $page );
	}

	/**
	 * How many rows a page holds.
	 *
	 * @param string $option   The user option name.
	 * @param int    $fallback The default.
	 *
	 * @return int
	 */
	protected function get_items_per_page( $option, $fallback = 20 ) {
		return (int) $fallback;
	}

	/**
	 * The action the request is asking for.
	 *
	 * @return string|false
	 */
	public function current_action() {
		if ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			return $_REQUEST['action'];
		}

		return false;
	}

	/**
	 * The first column, which carries the row actions.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		$columns = $this->get_columns();

		unset( $columns['cb'] );

		return (string) array_key_first( $columns );
	}

	/**
	 * The table's classes.
	 *
	 * @return string[]
	 */
	protected function get_table_classes() {
		return [ 'widefat', 'fixed', 'striped' ];
	}

	/**
	 * The columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return [];
	}

	/**
	 * Whether there is anything to show.
	 *
	 * @return bool
	 */
	public function has_items() {
		return ! empty( $this->items );
	}

	/**
	 * Row actions, as core renders them.
	 *
	 * @param array<string, string> $actions The actions.
	 * @param bool                  $always  Whether to always show them.
	 *
	 * @return string
	 */
	protected function row_actions( $actions, $always = false ) {
		if ( empty( $actions ) ) {
			return '';
		}

		$rendered = [];

		foreach ( $actions as $action => $link ) {
			$rendered[] = sprintf( '<span class="%s">%s</span>', $action, $link );
		}

		return '<div class="row-actions">' . implode( ' | ', $rendered ) . '</div>';
	}

	/**
	 * One row's cells.
	 *
	 * @param mixed $item The row.
	 *
	 * @return void
	 */
	protected function single_row_columns( $item ) {
		echo '<td>row</td>';
	}

	/**
	 * The table.
	 *
	 * @return void
	 */
	public function display() {
		echo '<table class="' . esc_attr( implode( ' ', $this->get_table_classes() ) ) . '"></table>';
	}
}
