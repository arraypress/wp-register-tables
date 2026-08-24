<?php
/**
 * Stylesheet tests.
 *
 * @package ArrayPress\RegisterTables
 */

declare( strict_types=1 );

namespace ArrayPress\RegisterTables\Tests;

use PHPUnit\Framework\TestCase;

/**
 * CSS has no errors, so a stylesheet is where a mistake lives longest.
 *
 * This library styles core's own list table rather than drawing one, so most
 * of what could go wrong is fighting core rather than failing on its own.
 */
final class StylesheetTest extends TestCase {

	/**
	 * The stylesheet.
	 *
	 * @return string
	 */
	private function css(): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/admin-tables.css' );
	}

	/**
	 * Every brace is closed.
	 */
	public function test_the_braces_balance(): void {
		$css = $this->css();

		$this->assertSame( substr_count( $css, '{' ), substr_count( $css, '}' ) );
	}

	/**
	 * No comment sits between a comma and an opening brace.
	 *
	 * That merges two rules: the selectors before it keep matching but take
	 * the next rule's declarations.
	 */
	public function test_no_comment_sits_inside_a_selector_list(): void {
		$merged = [];

		preg_match_all( '/(?:^|\})([^{}]*)\{/', $this->css(), $rules );

		foreach ( $rules[1] as $selector ) {
			$before = explode( '/*', $selector )[0];

			if ( str_contains( $selector, '/*' ) && str_contains( $before, ',' ) ) {
				$merged[] = trim( explode( "\n", trim( $before ) )[0] );
			}
		}

		$this->assertSame( [], $merged, implode( ', ', $merged ) );
	}

	/**
	 * Row actions are reachable without a hover.
	 *
	 * The library hides them and reveals them on `tr:hover`, which is right
	 * on a desktop and puts every Edit and Delete link out of reach on a
	 * phone. Core shows them on narrow screens; this must not re-hide them.
	 */
	public function test_row_actions_are_visible_on_a_narrow_screen(): void {
		$css = $this->css();

		$narrow = strstr( $css, '@media screen and (max-width: 782px)' );

		$this->assertNotFalse( $narrow, 'There are no styles for a narrow screen.' );
		$this->assertMatchesRegularExpression(
			'/row-actions[^{]*\{[^}]*visibility:\s*visible/',
			(string) $narrow,
			'Row actions stay hidden on a phone, where nothing can hover to reveal them.'
		);
	}

	/**
	 * The narrow-screen block comes last.
	 *
	 * A media query carries no extra specificity — only later position — so
	 * an override inside one has to come after the rule it undoes. The
	 * row-action rule is the one that matters, and it sits above.
	 */
	public function test_the_narrow_screen_block_comes_after_what_it_overrides(): void {
		$css = $this->css();

		$this->assertGreaterThan(
			strrpos( $css, '.admin-table .wp-list-table tr:hover .row-actions' ),
			strpos( $css, '@media screen and (max-width: 782px)' ),
			'The narrow-screen block is above the rule it has to undo, so it loses.'
		);
	}
}
