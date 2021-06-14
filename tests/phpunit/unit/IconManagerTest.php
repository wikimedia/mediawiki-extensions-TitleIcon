<?php

/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace MediaWiki\Extension\TitleIcon\Test;

use Config;
use Language;
use MediaWiki\Extension\TitleIcon\Icon;
use MediaWiki\Extension\TitleIcon\IconManager;
use MediaWiki\Extension\TitleIcon\SMWInterface;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Linker\LinkRenderer;
use MediaWikiUnitTestCase;
use PageProps;
use Parser;
use PHPUnit\Framework\MockObject\MockObject;
use RepoGroup;
use Title;
use TitleParser;

class IconManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @var Title
	 */
	private $title;

	/**
	 * @var Title
	 */
	private $category;

	/**
	 * @var Title
	 */
	private $namespace;

	protected function setUp() : void {
		$this->title = $this->createNoOpMock( Title::class,
			[ 'getNamespace', 'getDBkey', 'getArticleID', '__sleep' ] );
		$this->title->method( 'getNamespace' )->willReturn( 0 );
		$this->title->method( 'getDBkey' )->willReturn( 'TestPage' );
		$this->title->method( 'getArticleID' )->willReturn( 1 );
		$this->title->method( '__sleep' )->willReturn( [] );
		$this->category = $this->createNoOpMock( Title::class,
			[ 'getNamespace', 'getDBkey', 'getArticleID', '__sleep' ] );
		$this->category->method( 'getNamespace' )->willReturn( 14 );
		$this->category->method( 'getDBkey' )->willReturn( 'TestCategory' );
		$this->category->method( 'getArticleID' )->willReturn( 3 );
		$this->category->method( '__sleep' )->willReturn( [] );
		$this->namespace = $this->createNoOpMock( Title::class,
			[ 'getNamespace', 'getDBkey', 'getArticleID', '__sleep' ] );
		$this->namespace->method( 'getNamespace' )->willReturn( 4 );
		$this->namespace->method( 'getDBkey' )->willReturn( '(Main)' );
		$this->namespace->method( 'getArticleID' )->willReturn( 2 );
		$this->namespace->method( '__sleep' )->willReturn( [] );
	}

	private function getSMWInterface( array $icons = [], array $hide = [] ) : SMWInterface {
		/** @var SMWInterface|MockObject $smwInterface */
		$smwInterface = $this->createMock( SMWInterface::class );
		$smwInterface->method( 'getPropertyValues' )
			->willReturnCallback( static function ( $page, $propertyName ) use ( $icons, $hide ) {
				if ( $propertyName === 'Hide Title Icon' ) {
					// category, page, all, or empty
					return $hide;
				}
				return $icons[IconManager::getKeyForPage( $page )];
			} );
		return $smwInterface;
	}

	private function getManager( Title $title, SMWInterface $smwInterface ) : IconManager {
		$config = $this->createNoOpMock( Config::class, [ 'get' ] );
		$config->method( 'get' )->willReturnCallback( static function ( $name ) {
			if ( $name === 'TitleIcon_HideTitleIconPropertyName' ) {
				return 'Hide Title Icon';
			}
			return 'Title Icon';
		} );

		$titleParser = $this->createNoOpMock( TitleParser::class, [ 'parseTitle' ] );
		$self = $this;
		$titleParser->method( 'parseTitle' )->willReturnCallback( static function ( $text, $ns ) use ( $self ) {
			switch ( $ns ) {
				case 0:
					return $self->title;
				case 4:
					return $self->namespace;
				case 14:
					return $self->category;
			}
		} );

		return new IconManager(
			$config,
			$this->createMock( Parser::class ),
			$titleParser,
			$this->createMock( Language::class ),
			$this->createMock( PageProps::class ),
			$this->createMock( RepoGroup::class ),
			$this->createMock( LinkRenderer::class ),
			$this->createMock( JsonCodec::class ),
			$smwInterface
		);
	}

	public function provideGetIcons() {
		$this->setUp();
		yield [
			$this->title,
			[ $this->category ],
			[
				'ns0:TestPage' => [ 'Icon.png' ],
				'ns14:TestCategory' => [],
				'ns4:(Main)' => []
			],
			[],
			[
				new Icon( $this->title, 'Icon.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$this->title,
			[ $this->category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ],
				'ns4:(Main)' => []
			],
			[],
			[
				new Icon( $this->title, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $this->category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$this->title,
			[ $this->category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ],
				'ns4:(Main)' => [ 'Icon3.png' ]
			],
			[],
			[
				new Icon( $this->title, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $this->namespace, 'Icon2.png', Icon::ICON_TYPE_FILE ),
				new Icon( $this->category, 'Icon3.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$this->title,
			[ $this->category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ],
				'ns4:(Main)' => []
			],
			[ 'category' ],
			[
				new Icon( $this->title, 'Icon1.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$this->title,
			[ $this->category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ],
				'ns4:(Main)' => []
			],
			[ 'page' ],
			[
				new Icon( $this->category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$this->title,
			[ $this->category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ],
				'ns4:(Main)' => []
			],
			[ 'all' ],
			[]
		];
		yield [
			$this->title,
			[ $this->category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ],
				'ns4:(Main)' => []
			],
			[ 'bogus' ],
			[
				new Icon( $this->title, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $this->category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			]
		];
	}

	/**
	 * @covers MediaWiki\Extension\TitleIcon\IconManager::getIcons
	 * @dataProvider provideGetIcons
	 */
	public function testGetIcons(
		Title $title,
		array $categories,
		array $smwIcons,
		array $hide,
		array $expected
	) {
		$smwInterface = $this->getSMWInterface( $smwIcons, $hide );
		$manager = $this->getManager( $title, $smwInterface );

		$icons = $manager->getIcons( $title, $categories );

		$this->assertArrayEquals(
			$icons,
			$expected
		);
	}
}
