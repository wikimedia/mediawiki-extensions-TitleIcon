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

use Config;
use HtmlArmor;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Search\Hook\ShowSearchHitTitleHook;
use OutputPage;
use SearchResult;
use Skin;
use SpecialSearch;
use Title;

class MainHookHandler implements BeforePageDisplayHook, ShowSearchHitTitleHook {
	/** @var IconManager */
	private $iconManager;

	/** @var Config */
	private $config;

	/** @var LinkRenderer */
	private $linkRenderer;

	/**
	 * @param IconManager $iconManager
	 * @param Config $config
	 * @param LinkRenderer $linkRenderer
	 */
	public function __construct(
		IconManager $iconManager,
		Config $config,
		LinkRenderer $linkRenderer
	) {
		$this->iconManager = $iconManager;
		$this->config = $config;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ) : void {
		if ( !$this->config->get( "TitleIcon_EnableIconInPageTitle" ) ) {
			return;
		}

		$title = $skin->getTitle();
		if ( $title->isSpecialPage() ) {
			return;
		}

		$this->iconManager->getIcons(
			$title
		);
		$html = $this->iconManager->getHTML( $title );
		if ( strlen( $html ) > 0 ) {
			$out->addJsConfigVars( 'TitleIconHTML', $html );
			$out->addJsConfigVars( 'TitleIconSelector', $this->config->get( 'TitleIcon_CSSSelector' ) );
			$out->addModules( 'ext.TitleIcon' );
		}
	}

	/**
	 * @param Title &$title
	 * @param HtmlArmor|string|null &$titleSnippet
	 * @param SearchResult $result
	 * @param array $terms
	 * @param SpecialSearch $specialSearch
	 * @param string[] &$query
	 * @param string[] &$attributes
	 * @return bool|void
	 */
	public function onShowSearchHitTitle(
		&$title, &$titleSnippet, $result, $terms, $specialSearch, &$query, &$attributes
	) {
		if ( !$this->config->get( "TitleIcon_EnableIconInSearchTitle" ) ) {
			return;
		}

		$this->iconManager->getIcons(
			$title
		);
		$html = $this->iconManager->getHTML( $title );
		if ( strlen( $html ) > 0 ) {
			$titleSnippet = new HtmlArmor( $html . $this->linkRenderer->makeLink( $title ) );
		}
	}
}
