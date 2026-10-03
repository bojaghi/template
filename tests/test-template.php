<?php

namespace Bojaghi\Template\Tests;

use WP_UnitTestCase;
use Bojaghi\Template\Template;

class Template_Test extends WP_UnitTestCase {
	public function test_template() {
		$t = new Template( array( 'scopes' => array( __DIR__ . '/test-files' ) ) );

		$this->assertEquals(
			'<div id="1"></div>' . PHP_EOL .
			'<div id="2"></div>' . PHP_EOL .
			'<div id="3"></div>' . PHP_EOL .
			'<p>Hello! I am parent template!</p>' . PHP_EOL,
			$t->template( 'sample-01' ),
		);

		$this->assertEquals(
			'<div id="1">bar</div>' . PHP_EOL .
			'<div id="2">rock</div>' . PHP_EOL .
			'<div id="3">summer</div>' . PHP_EOL .
			'<p>Hello! I am child template!</p>' . PHP_EOL .
			'<footer></footer>' . PHP_EOL,
			$t->template(
				'sample-02',
				array(
					'foo'    => 'bar',
					'genre'  => 'rock',
					'season' => 'summer',
				),
			),
		);
	}
}
