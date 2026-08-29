<?php
/**
 * Bulk action tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Manager;
use PHPUnit\Framework\TestCase;

/**
 * Bulk actions are the destructive end of a list table.
 *
 * They are reached from `admin_init` by matching `?page=`, before the page
 * that would normally gate them has rendered — so every check has to be here.
 * Three of them: is this user allowed, did the request come from our form, and
 * did anything actually get selected.
 */
final class BulkActionTest extends TestCase {

	/**
	 * A clean request between tests.
	 */
	protected function setUp(): void {
		rt_reset_globals();

		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
	}

	/**
	 * Register a table whose delete action records what it was given.
	 *
	 * @param array<string, mixed> $config Extra configuration.
	 * @param array|null           $ran    Filled with the ids the action ran on.
	 *
	 * @return void
	 */
	private function register( array $config, ?array &$ran ): void {
		$ran = null;

		Manager::register(
			'demo',
			array_merge(
				[
					'menu_slug'    => 'demo',
					'labels'       => [ 'singular' => 'item', 'plural' => 'items' ],
					'columns'      => [ 'name' => 'Name' ],
					'bulk_actions' => [
						'delete' => [
							'label'    => 'Delete',
							'callback' => function ( $ids ) use ( &$ran ) {
								$ran = $ids;

								return true;
							},
						],
					],
				],
				$config
			)
		);
	}

	/**
	 * A request selecting two items for deletion.
	 *
	 * @return void
	 */
	private function submit(): void {
		$_GET['page']         = 'demo';
		$_REQUEST['page']     = 'demo';
		$_REQUEST['action']   = 'delete';
		$_REQUEST['items']    = [ '5', '7' ];
		$_REQUEST['_wpnonce'] = 'nonce';

		$this->dispatch();
	}

	/**
	 * Dispatch, absorbing the redirect a completed action ends with.
	 *
	 * Every action here finishes `wp_safe_redirect(); exit;`. The stub throws
	 * instead, so the exit is never reached and the assertions can run.
	 *
	 * @return string|null Where it redirected, or null if it did not.
	 */
	private function dispatch(): ?string {
		try {
			Manager::process_early_actions();
		} catch ( \RT_Redirected $redirect ) {
			return $redirect->location;
		}

		return null;
	}

	/**
	 * The selected ids reach the callback.
	 *
	 * As integers, and all of them. The checkboxes post an array, and
	 * `sanitize_text_field()` hands back an empty string when given one — so
	 * sanitizing the array rather than its members drops every selection and
	 * makes the action quietly do nothing.
	 */
	public function test_the_selected_ids_reach_the_callback(): void {
		$this->register( [], $ran );
		$this->submit();

		$this->assertSame( [ 5, 7 ], $ran );
	}

	/**
	 * A user without the bulk capability is refused.
	 *
	 * `get_bulk_actions()` checks the same capability, but that only decides
	 * whether the dropdown is drawn — hiding the control rather than refusing
	 * the action. The nonce is a CSRF check and not an authorisation one, and
	 * it is scoped to the plural label, so two tables sharing one accept each
	 * other's.
	 */
	public function test_a_user_without_the_capability_is_refused(): void {
		$this->register( [ 'capabilities' => [ 'bulk' => 'delete_things' ] ], $ran );

		$GLOBALS['rt_caps'] = [ 'read' ];

		// Told, rather than quietly ignored: a silently dropped action is
		// indistinguishable from one that succeeded and did nothing.
		$this->expectException( \RuntimeException::class );

		try {
			$this->submit();
		} finally {
			$this->assertNull( $ran, 'A bulk action ran for a user who may not run it.' );
		}
	}

	/**
	 * And with it, allowed.
	 */
	public function test_a_user_with_the_capability_is_allowed(): void {
		$this->register( [ 'capabilities' => [ 'bulk' => 'delete_things' ] ], $ran );

		$GLOBALS['rt_caps'] = [ 'delete_things' ];

		$this->submit();

		$this->assertSame( [ 5, 7 ], $ran );
	}

	/**
	 * The table's own capability is the default.
	 *
	 * A table that names a capability and no per-action ones should not have
	 * its bulk actions open to anyone who can reach the page.
	 */
	public function test_the_tables_capability_is_the_default(): void {
		$this->register( [ 'capability' => 'manage_shop' ], $ran );

		$GLOBALS['rt_caps'] = [ 'read' ];

		$this->expectException( \RuntimeException::class );

		try {
			$this->submit();
		} finally {
			$this->assertNull( $ran );
		}
	}

	/**
	 * An action can demand more than the table does.
	 */
	public function test_an_action_can_demand_its_own_capability(): void {
		Manager::register(
			'demo',
			[
				'menu_slug'    => 'demo',
				'labels'       => [ 'singular' => 'item', 'plural' => 'items' ],
				'columns'      => [ 'name' => 'Name' ],
				'capability'   => 'read',
				'bulk_actions' => [
					'delete' => [
						'label'      => 'Delete',
						'capability' => 'delete_things',
						'callback'   => static fn() => true,
					],
				],
			]
		);

		$GLOBALS['rt_caps'] = [ 'read' ];

		// Past the table's capability, which it has, and stopped by the
		// action's, which it does not.
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'not allowed' );

		$this->submit();
	}

	/**
	 * A bad nonce stops it.
	 */
	public function test_a_bad_nonce_stops_it(): void {
		$this->register( [], $ran );

		$GLOBALS['rt_nonce_ok'] = false;

		$this->submit();

		$this->assertNull( $ran );
	}

	/**
	 * A missing nonce stops it.
	 */
	public function test_a_missing_nonce_stops_it(): void {
		$this->register( [], $ran );

		$_GET['page']       = 'demo';
		$_REQUEST['page']   = 'demo';
		$_REQUEST['action'] = 'delete';
		$_REQUEST['items']  = [ '5' ];

		$this->dispatch();

		$this->assertNull( $ran );
	}

	/**
	 * Nothing selected does nothing.
	 *
	 * Pressing Apply with no rows ticked should not be a delete-everything.
	 */
	public function test_nothing_selected_does_nothing(): void {
		$this->register( [], $ran );

		$_GET['page']         = 'demo';
		$_REQUEST['page']     = 'demo';
		$_REQUEST['action']   = 'delete';
		$_REQUEST['_wpnonce'] = 'nonce';

		$this->dispatch();

		$this->assertNull( $ran );
	}

	/**
	 * The dropdown's own "no action" value does nothing.
	 */
	public function test_the_no_action_value_does_nothing(): void {
		$this->register( [], $ran );

		$_GET['page']         = 'demo';
		$_REQUEST['page']     = 'demo';
		$_REQUEST['action']   = '-1';
		$_REQUEST['items']    = [ '5' ];
		$_REQUEST['_wpnonce'] = 'nonce';

		$this->dispatch();

		$this->assertNull( $ran );
	}

	/**
	 * The second dropdown works too.
	 *
	 * Core prints one above the table and one below, and the lower one posts
	 * `action2`. A table that only read `action` worked from the top and
	 * silently did nothing from the bottom.
	 */
	public function test_the_lower_dropdown_works(): void {
		$this->register( [], $ran );

		$_GET['page']         = 'demo';
		$_REQUEST['page']     = 'demo';
		$_REQUEST['action']   = '-1';
		$_REQUEST['action2']  = 'delete';
		$_REQUEST['items']    = [ '5' ];
		$_REQUEST['_wpnonce'] = 'nonce';

		$this->dispatch();

		$this->assertSame( [ 5 ], $ran );
	}

	/**
	 * An action nobody declared does nothing.
	 */
	public function test_an_undeclared_action_does_nothing(): void {
		$this->register( [], $ran );

		$_GET['page']         = 'demo';
		$_REQUEST['page']     = 'demo';
		$_REQUEST['action']   = 'something_else';
		$_REQUEST['items']    = [ '5' ];
		$_REQUEST['_wpnonce'] = 'nonce';

		$this->dispatch();

		$this->assertNull( $ran );
	}

	/**
	 * A request for another page leaves this table alone.
	 */
	public function test_another_page_is_not_this_table(): void {
		$this->register( [], $ran );

		$_GET['page']         = 'somewhere_else';
		$_REQUEST['page']     = 'somewhere_else';
		$_REQUEST['action']   = 'delete';
		$_REQUEST['items']    = [ '5' ];
		$_REQUEST['_wpnonce'] = 'nonce';

		$this->dispatch();

		$this->assertNull( $ran );
	}

	/* =========================================================================
	 * Bulk edit is not a bulk action
	 * ========================================================================= */

	/**
	 * A bulk edit reaches the edit handler, not the bulk action machinery.
	 *
	 * "Edit" has to sit in the bulk actions dropdown, because that dropdown
	 * is what opens the inline row -- but it is not an action. Letting it
	 * fall through to process_bulk_actions() meant the action hooks fired,
	 * the request redirected saying "1 updated", and it exited before the
	 * edit was applied. The notice said it had worked; nothing had changed.
	 *
	 * Neither half of this is visible from a unit test of either function.
	 * It only appears when both run, in order, against one request.
	 */
	public function test_a_bulk_edit_is_not_run_as_a_bulk_action(): void {
		$acted = null;
		$edited = null;

		$this->register(
			[
				'bulk_actions' => [ 'edit' => 'Bulk edit' ],
				'bulk_edit'    => [
					'status' => [
						'label'   => 'Status',
						'options' => [ 'active' => 'Active', 'draft' => 'Draft' ],
					],
				],
			],
			$ran
		);

		add_action(
			'arraypress_table_bulk_action_demo',
			static function ( $ids, $action ) use ( &$acted ): void {
				$acted = $action;
			}
		);

		add_action(
			'arraypress_table_bulk_edit_demo',
			static function ( $ids, $values ) use ( &$edited ): void {
				$edited = [ $ids, $values ];
			}
		);

		$_GET['page']                 = 'demo';
		$_REQUEST['page']             = 'demo';
		$_REQUEST['action']           = 'edit';
		$_REQUEST['bulk_edit']        = 'Update';
		$_REQUEST['table_id']         = 'demo';
		$_REQUEST['items']            = [ '5', '7' ];
		$_REQUEST['status']           = 'draft';
		$_REQUEST['_wpnonce']         = 'nonce';
		$_REQUEST['_bulk_edit_nonce'] = 'nonce';

		$location = $this->dispatch();

		$this->assertNull( $acted, 'edit must not run as a bulk action' );
		$this->assertSame( [ [ 5, 7 ], [ 'status' => 'draft' ] ], $edited );

		// And it ends the way every other bulk action ends, so a refresh does
		// not apply the edit a second time.
		$this->assertNotNull( $location );
		$this->assertStringContainsString( 'updated=2', (string) $location );
	}

	/**
	 * A table with no inline editing keeps its own 'edit' action.
	 *
	 * The carve-out is for the inline editor, not for the word.
	 */
	public function test_a_plain_edit_action_still_runs(): void {
		$acted = null;

		$this->register( [ 'bulk_actions' => [ 'edit' => 'Edit' ] ], $ran );

		add_action(
			'arraypress_table_bulk_action_demo',
			static function ( $ids, $action ) use ( &$acted ): void {
				$acted = $action;
			}
		);

		$_GET['page']         = 'demo';
		$_REQUEST['page']     = 'demo';
		$_REQUEST['action']   = 'edit';
		$_REQUEST['items']    = [ '5' ];
		$_REQUEST['_wpnonce'] = 'nonce';

		$this->dispatch();

		$this->assertSame( 'edit', $acted );
	}
}
