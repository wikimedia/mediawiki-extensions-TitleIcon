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
		$config = $this->createMock( Config::class );
		$config->method( 'get' )->willReturnCallback( static function ( $name ) {
			if ( $name === 'TitleIcon_HideTitleIconPropertyName' ) {
				return 'Hide Title Icon';
			}
			return 'Title Icon';
		} );

		return new IconManager(
			$config,
			$this->createMock( Parser::class ),
			$this->createMock( TitleParser::class ),
			$this->createMock( PageProps::class ),
			$this->createMock( RepoGroup::class ),
			$this->createMock( LinkRenderer::class ),
			$this->createMock( JsonCodec::class ),
			$smwInterface
		);
	}

	public function provideGetIcons() {
		/** @var Title|MockObject $title */
		$title = $this->createNoOpMock( Title::class, [ 'getNamespace', 'getDBkey', 'getArticleID', '__sleep' ] );
		$title->method( 'getNamespace' )->willReturn( 0 );
		$title->method( 'getDBkey' )->willReturn( 'TestPage' );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( '__sleep' )->willReturn( [] );
		$category = $this->createNoOpMock( Title::class, [ 'getNamespace', 'getDBkey', 'getArticleID', '__sleep' ] );
		$category->method( 'getNamespace' )->willReturn( 14 );
		$category->method( 'getDBkey' )->willReturn( 'TestCategory' );
		$category->method( 'getArticleID' )->willReturn( 2 );
		$category->method( '__sleep' )->willReturn( [] );

		yield [
			$title,
			[ $category ],
			[
				'ns0:TestPage' => [ 'Icon.png' ],
				'ns14:TestCategory' => []
			],
			[],
			[
				new Icon( $title, 'Icon.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$title,
			[ $category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ]
			],
			[],
			[
				new Icon( $title, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$title,
			[ $category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ]
			],
			[ 'category' ],
			[
				new Icon( $title, 'Icon1.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$title,
			[ $category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ]
			],
			[ 'page' ],
			[
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			]
		];
		yield [
			$title,
			[ $category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ]
			],
			[ 'all' ],
			[]
		];
		yield [
			$title,
			[ $category ],
			[
				'ns0:TestPage' => [ 'Icon1.png' ],
				'ns14:TestCategory' => [ 'Icon2.png' ]
			],
			[ 'bogus' ],
			[
				new Icon( $title, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE )
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
