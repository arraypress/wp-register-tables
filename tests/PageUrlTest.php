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
	 * Nothing builds an address any other way.
	 *
	 * The bug was eight separate copies of the same wrong line, so what is
	 * asserted is that there is one place left that decides this.
	 */
	public function test_no_url_is_built_against_admin_php_directly(): void {
		foreach ( [ 'Manager.php', 'Table.php' ] as $file ) {
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
