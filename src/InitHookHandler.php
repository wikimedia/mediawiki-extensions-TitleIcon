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

namespace MediaWiki\Extension\TitleIcon;

use MalformedTitleException;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use Parser;

class InitHookHandler implements ParserFirstCallInitHook {
	/**
	 * @param Parser $parser
	 * @return bool|void
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'hidetitleicon',
			[ self::class, 'hideTitleIcon' ]
		);
		$parser->setFunctionHook(
			'titleicon_file',
			[ self::class, 'handleFile' ]
		);
		$parser->setFunctionHook(
			'titleicon_ooui',
			[ self::class, 'handleOOUI' ]
		);
		$parser->setFunctionHook(
			'titleicon_unicode',
			[ self::class, 'handleUnicode' ]
		);
	}

	/**
	 * @param Parser $parser
	 * @param string|null $flag 'page', 'category', 'namespace', or 'all'
	 */
	public static function hideTitleIcon( Parser $parser, ?string $flag = null ) {
		MediaWikiServices::getInstance()->getService( "TitleIcon:IconManager" )->hideTitleIcon( $flag );
	}

	/**
	 * @param Parser $parser
	 * @param string|null $icon
	 * @param string|null $link
	 */
	public static function handleFile( Parser $parser, ?string $icon = null, ?string $link = null ) {
		MediaWikiServices::getInstance()->getService( "TitleIcon:IconManager" )->parseIcons(
			$parser->getPage(),
			Icon::ICON_TYPE_FILE,
			$icon,
			self::linkToPageReference( $link )
		);
	}

	/**
	 * @param Parser $parser
	 * @param string|null $icon
	 * @param string|null $link
	 */
	public static function handleOOUI( Parser $parser, ?string $icon = null, ?string $link = null ) {
		MediaWikiServices::getInstance()->getService( "TitleIcon:IconManager" )->parseIcons(
			$parser->getPage(),
			Icon::ICON_TYPE_OOUI,
			$icon,
			self::linkToPageReference( $link )
		);
	}

	/**
	 * @param Parser $parser
	 * @param string|null $icon
	 * @param string|null $link
	 */
	public static function handleUnicode( Parser $parser, ?string $icon = null, ?string $link = null ) {
		MediaWikiServices::getInstance()->getService( "TitleIcon:IconManager" )->parseIcons(
			$parser->getPage(),
			Icon::ICON_TYPE_UNICODE,
			$icon,
			self::linkToPageReference( $link )
		);
	}

	/**
	 * @param string|null $link
	 * @return PageReference|null
	 */
	private static function linkToPageReference( ?string $link ) {
		if ( $link ) {
			try {
				$title = MediaWikiServices::getInstance()->getTitleParser()->parseTitle( $link );
				return PageReferenceValue::localReference( $title->getNamespace(), $title->getDBkey() );
			} catch ( MalformedTitleException $exception ) {
				return null;
			}
		}
		return null;
	}
}
