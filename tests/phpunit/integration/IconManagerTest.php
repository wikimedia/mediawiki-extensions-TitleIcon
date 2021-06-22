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

use MediaWiki\Extension\TitleIcon\Icon;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use PageProps;
use TitleValue;

/**
 * @group Database
 */
class IconManagerTest extends MediaWikiIntegrationTestCase {
	/**
	 * @var string[]
	 */
	protected $tablesUsed = [ 'page' ];

	private function setPageContent(
		string $testPageContent,
		string $testCategoryContent = null,
		string $testNamespaceContent = null
	) : array {
		$result = [];
		if ( $testNamespaceContent ) {
			$result['namespace'] = $this->insertPage( '(Main)', $testNamespaceContent, NS_PROJECT )['title'];
		}
		if ( $testCategoryContent ) {
			$result['category'] = $this->insertPage( 'Test Category', $testCategoryContent, NS_CATEGORY )['title'];
			$testPageContent .= '[[Category:Test Category]]';
		}
		$result['page'] = $this->insertPage( 'Test Page', $testPageContent, NS_MAIN )['title'];
		return $result;
	}

	public function provideGetIcons() {
		$page = new TitleValue( NS_MAIN, 'Test Page' );
		$category = new TitleValue( NS_CATEGORY, 'Test Category' );
		$namespace = new TitleValue( NS_PROJECT, '(Main)' );
		$link = new TitleValue( NS_MAIN, 'Another Page' );
		yield [
			'{{#titleicon_file:Icon1.png}}',
			null,
			null,
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE )
			],
			'page icon'
		];
		yield [
			'',
			'{{#titleicon_file:Icon2.png}}',
			null,
			[
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			],
			'category icon'
		];
		yield [
			'',
			null,
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'namespace icon'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}',
			'{{#titleicon_file:Icon2.png}}',
			null,
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			],
			'page and category icons'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}',
			null,
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'page and namespace icons'
		];
		yield [
			'',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE ),
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'category and namespace icons'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE ),
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'page, category, and namespace icons'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}{{#hidetitleicon:page}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE ),
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'hide page icon'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}{{#hidetitleicon:category}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'hide category icon'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}{{#hidetitleicon:namespace}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE )
			],
			'hide namespace icon'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}{{#hidetitleicon:category}}{{#hidetitleicon:namespace}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE )
			],
			'multiple hides'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}{{#hidetitleicon:all}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
			],
			'hide all icons'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}'
			. '{{#hidetitleicon:page}}{{#hidetitleicon:category}}{{#hidetitleicon:namespace}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
			],
			'hide all icons'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}{{#hidetitleicon:bogus}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE ),
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE ),
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'bogus hide value ignores hide'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}{{#hidetitleicon:page}}{{#hidetitleicon:bogus}}',
			'{{#titleicon_file:Icon2.png}}',
			'{{#titleicon_file:Icon3.png}}',
			[
				new Icon( $category, 'Icon2.png', Icon::ICON_TYPE_FILE ),
				new Icon( $namespace, 'Icon3.png', Icon::ICON_TYPE_FILE )
			],
			'bogus hide value ignores hide with valid hide'
		];
		yield [
			'{{#titleicon_file:File:Icon1.png}}',
			null,
			null,
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE )
			],
			'can add File: to filename for file icon in parser function'
		];
		yield [
			'{{#titleicon_file:Icon1.png}}',
			null,
			null,
			[
				new Icon( $page, 'File:Icon1.png', Icon::ICON_TYPE_FILE )
			],
			'can add File: to filename for file icon in Icon constructor'
		];
		yield [
			'{{#titleicon_unicode:&#x1F469;&#x1F3FE;&zwj;&#x1F4BB;}}',
			null,
			null,
			[
				new Icon( $page, '&#x1F469;&#x1F3FE;&zwj;&#x1F4BB;', Icon::ICON_TYPE_UNICODE )
			],
			'unicode icon'
		];
		yield [
			'{{#titleicon_ooui:wikiText.svg}}}',
			null,
			null,
			[
				new Icon( $page, 'wikiText.svg', Icon::ICON_TYPE_OOUI )
			],
			'OOUI icon'
		];
		yield [
			'{{#titleicon_file:Icon1.png|Another Page}}',
			null,
			null,
			[
				new Icon( $page, 'Icon1.png', Icon::ICON_TYPE_FILE, $link )
			],
			'icon with page link'
		];
	}

	private function getServiceContainer() {
		return MediaWikiServices::getInstance();
	}

	/**
	 * @covers       MediaWiki\Extension\TitleIcon\IconManager::getIcons
	 * @dataProvider provideGetIcons
	 * @param string $testPageContent
	 * @param string|null $testCategoryContent
	 * @param string|null $testNamespaceContent
	 * @param array $expected
	 * @param string $message
	 */
	public function testGetIcons(
		string $testPageContent,
		?string $testCategoryContent,
		?string $testNamespaceContent,
		array $expected,
		string $message
	) {
		$oldPageProps = PageProps::overrideInstance( null );
		$manager = $this->getServiceContainer()->getService( 'TitleIcon:IconManager' );
		$jsonCodec = $this->getServiceContainer()->get( 'TitleIcon:JsonCodec' );
		$title = $this->setPageContent( $testPageContent, $testCategoryContent, $testNamespaceContent )['page'];

		$actual = $manager->getIcons( $title );

		$this->assertEquals(
			$jsonCodec->serialize( $expected ),
			$jsonCodec->serialize( $actual ),
			$message
		);
	}
}
