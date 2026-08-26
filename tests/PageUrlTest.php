<?php
/**
 * Page address tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use ArrayPress\RegisterTables\Manager;
use PHPUnit\Framework\TestCase;

/**
 * Every URL this library builds — the view tabs, the filter links, the search
 * form's action, every post-action redirect — used to be hardcoded to
 * admin.php. That is right for a table with no parent and wrong for every
 * other one: WordPress serves a submenu page from whatever file its parent
 * slug names.
 *
 * A table under `edit.php?post_type=book` lives at
 * `edit.php?post_type=book&page=my-table`, and `admin.php?page=my-table`
 * answers "Sorry, you are not allowed to access this page." Which is exactly
 * what a bulk delete did, because the redirect it built dropped the post type
 * on the way back.
 */
final class PageUrlTest extends TestCase {

	/**
	 * Reset the stubbed globals.
	 */
	protected function setUp(): void {
		rt_reset_globals();
	}

	/**
	 * The page's address, for a given parent.
	 *
	 * @dataProvider parentProvider
	 *
	 * @param string $parent   The parent slug.
	 * @param string $expected The address it should produce.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider( 'parentProvider' )]
	public function test_the_address_follows_the_parent( string $parent, string $expected ): void {
		$this->assertSame(
			$expected,
			Manager::page_url( [ 'parent_slug' => $parent, 'menu_slug' => 'my-table' ] )
		);
	}

	/**
	 * One case per shape a parent slug comes in.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function parentProvider(): array {
		return [
			'no parent'          => [ '', 'https://example.test/wp-admin/admin.php?page=my-table' ],
			'a core file'        => [ 'tools.php', 'https://example.test/wp-admin/tools.php?page=my-table' ],
			'a post type'        => [
				'edit.php?post_type=book',
				'https://example.test/wp-admin/edit.php?post_type=book&page=my-table',
			],
			'another plugin page' => [
				'my-plugin',
				'https://example.test/wp-admin/admin.php?page=my-table',
			],
		];
	}

	/**
	 * Extra arguments are added, and the parent's are kept.
	 *
	 * The bulk-action redirect is this case: it carries its result back as
	 * query arguments, and dropping the post type on the way is what made the
	 * page it landed on refuse to load.
	 */
	public function test_extra_arguments_do_not_displace_the_parents(): void {
		$url = Manager::page_url(
			[ 'parent_slug' => 'edit.php?post_type=book', 'menu_slug' => 'my-table' ],
			[ 'deleted' => 3 ]
		);

		$this->assertStringContainsString( 'post_type=book', $url );
		$this->assertStringContainsString( 'page=my-table', $url );
		$this->assertStringContainsString( 'deleted=3', $url );
		$this->assertStringStartsWith( 'https://example.test/wp-admin/edit.php?', $url );
	}

	/**
	 * The arguments that identify a page, for the form to carry.
	 *
	 * A GET form replaces the query string outright, so anything not written
	 * into it as a hidden input is gone the moment it submits. For a table
	 * under a post type that means post_type -- and losing it is losing the
	 * screen, because WordPress builds the page hook from $pagenow plus
	 * $typenow and without it answers "Sorry, you are not allowed to access
	 * this page". Which is what every bulk action and every filter did.
	 */
	public function test_page_args_carry_the_parents_own_arguments(): void {
		$this->assertSame(
			[ 'post_type' => 'book', 'page' => 'my-table' ],
			Manager::page_args( [ 'parent_slug' => 'edit.php?post_type=book', 'menu_slug' => 'my-table' ] )
		);
	}

	/**
	 * A table at the top level needs nothing but its own slug.
	 */
	public function test_page_args_for_a_top_level_table(): void {
		$this->assertSame(
			[ 'page' => 'my-table' ],
			Manager::page_args( [ 'menu_slug' => 'my-table' ] )
		);
	}

	/**
	 * A parent that is itself a plugin page does not pass its own `page` on.
	 *
	 * `admin.php?page=my-plugin` is the parent; the submenu lives at
	 * `admin.php?page=my-table`. Carrying the parent's `page` too would post
	 * the key twice and land on the parent.
	 */
	public function test_a_plugin_page_parent_does_not_pass_its_page_through(): void {
		$args = Manager::page_args(
			[ 'parent_slug' => 'admin.php?page=my-plugin', 'menu_slug' => 'my-table' ]
		);

		$this->assertSame( [ 'page' => 'my-table' ], $args );
	}

	/**
	 * The form is built from that, not from a second list.
	 *
	 * The original bug was one hardcoded `page` input and nothing else, in a
	 * file nobody thought of as building a URL. Asserting the derivation is
	 * what stops it being reintroduced by someone adding an argument to
	 * page_url() and not to the form.
	 */
	public function test_the_form_takes_its_hidden_inputs_from_page_args(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Traits/PageRenderer.php' );

		$this->assertStringContainsString( 'self::page_args( $config )', $source );

		$this->assertDoesNotMatchRegularExpression(
			'/<input type="hidden" name="page"/',
			$source,
			'The form hardcodes its page argument instead of deriving it.'
		);
	}

	/**
	 * A filter is a control in the form, not also a hidden input beside it.
	 *
	 * Both would post the same key twice.
	 */
	public function test_filters_are_not_duplicated_as_hidden_inputs(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Traits/PageRenderer.php' );

		$this->assertStringNotContainsString( '$preserve_params', $source );
	}

	/**
	 * Nothing builds an address any other way.
	 *
	 * The bug was eight separate copies of the same wrong line, so what is
	 * asserted is that there is one place left that decides this.
	 */
	public function test_no_url_is_built_against_admin_php_directly(): void {
		foreach ( [ 'Manager.php', 'Table.php', 'Traits/Urls.php' ] as $file ) {
			$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/' . $file );

			$this->assertSame(
				0,
				substr_count( $source, "admin_url( 'admin.php' )" ),
				sprintf(
					'%s builds a page address itself. Use Manager::page_url(), or a table under '
					. 'a parent that is not the top level will link to a page that refuses to load.',
					$file
				)
			);
		}
	}
}
