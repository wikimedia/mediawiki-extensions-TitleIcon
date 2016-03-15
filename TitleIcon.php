<?php

/*
 * Copyright (c) 2013-2016 The MITRE Corporation
 *
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

class TitleIcon {

	private static $m_already_invoked = false;

	/**
	 * @since 1.0
	 *
	 * @param Parser &$parser
	 */
	public static function setup( Parser &$parser ) {

		if ( $GLOBALS['wgTitleIcon_EnableIconInPageTitle'] ) {

			$GLOBALS['wgHooks']['BeforePageDisplay'][] =
				'TitleIcon::showIconInPageTitle';

		}

		if ( $GLOBALS['wgTitleIcon_EnableIconInSearchTitle'] ) {

			$GLOBALS['wgHooks']['ShowSearchHitTitle'][] =
				'TitleIcon::showIconInSearchTitle';

		}

		return true;
	}

	/**
	 * @since 1.0
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function showIconInPageTitle( OutputPage &$out,
		Skin &$skin ) {

		if ( self::$m_already_invoked ) {
			return true;
		}
		self::$m_already_invoked = true;

		$instance = new self( $skin->getTitle() );

		$instance->setConfiguration(
			$GLOBALS['wgTitleIcon_CSSSelector'],
			$GLOBALS['wgTitleIcon_UseFileNameAsToolTip'],
			$GLOBALS['wgTitleIcon_TitleIconPropertyName'],
			$GLOBALS['wgTitleIcon_HideTitleIconPropertyName']
		);

		$instance->handlePageTitle( $out );

		return true;

	}

	/**
	 * @since 1.0
	 *
	 * @param Title &$title
	 * @param &$text
	 * @param SearchResult $result
	 * @param array $terms,
	 * @param SpecialSearch $page
	 */
	public static function showIconInSearchTitle( Title &$title,
		&$text, SearchResult $result, array $terms, SpecialSearch $page ) {

		$instance = new self( $title );

		$instance->setConfiguration(
			$GLOBALS['wgTitleIcon_CSSSelector'],
			$GLOBALS['wgTitleIcon_UseFileNameAsToolTip'],
			$GLOBALS['wgTitleIcon_TitleIconPropertyName'],
			$GLOBALS['wgTitleIcon_HideTitleIconPropertyName']
		);

		$instance->handleSearchTitle( $text );

		return true;

	}

	private $title;
	private $cssSelector;
	private $useFileNameAsToolTip;
	private $titleIconPropertyName;
	private $hideTitleIconPropertyName;

	/**
	 * @since 1.0
	 *
	 * @param Title $title
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
	}

	/**
	 * @since 1.0
	 *
	 * @param $cssSelector
	 * @param $useFileNameAsToolTip
	 * @param $titleIconPropertyName,
	 * @param $hideTitleIconPropertyName
	 *
	 */
	public function setConfiguration( $cssSelector, $useFileNameAsToolTip,
		$titleIconPropertyName, $hideTitleIconPropertyName ) {
		$this->cssSelector = $cssSelector;
		$this->useFileNameAsToolTip = $useFileNameAsToolTip;
		$this->titleIconPropertyName = $titleIconPropertyName;
		$this->hideTitleIconPropertyName = $hideTitleIconPropertyName;
	}

	/**
	 * @since 1.0
	 *
	 * @param OutputPage $out
	 */
	public function handlePageTitle( OutputPage $out ) {
		$iconhtml = $this->getIconHTML();
		if ( strlen( $iconhtml ) > 0 ) {
			$out->addJsConfigVars( 'TitleIconHTML', $iconhtml );
			$out->addJsConfigVars( 'TitleIconSelector', $this->cssSelector );
			$out->addModules( 'ext.TitleIcon' );
		}
	}

	/**
	 * @since 1.0
	 *
	 * @param &$text
	 */
	public function handleSearchTitle( &$text ) {
		$iconhtml = $this->getIconHTML();
		if ( strlen( $iconhtml ) > 0 ) {
			$text = $iconhtml . Linker::link( $this->title );
		}
	}

	private function getIconHTML() {

		$icons = $this->getIcons();

		$iconhtml = "";
		foreach ( $icons as $iconinfo ) {

			$page = $iconinfo["page"];
			$icon = $iconinfo["icon"];

			$filetitle = Title::newFromText( "File:" . $icon );
			$imagefile = wfFindFile( $filetitle );

			if ( $imagefile !== false ) {

				if ( $this->useFileNameAsToolTip ) {
					$tooltip = $icon;
					if ( strpos( $tooltip, '.' ) !== false ) {
						$tooltip =
							substr( $tooltip, 0, strpos( $tooltip, '.' ) );
					}
				} else {
					$tooltip = $page;
				}

				$frameParams = array();
				$frameParams['link-title'] = $page;
				$frameParams['alt'] = $tooltip;
				$frameParams['title'] = $tooltip;
				$handlerParams = array(
					'width' => '36',
					'height' => '36'
				);

				$iconhtml .= Linker::makeImageLink( $GLOBALS['wgParser'],
					$filetitle, $imagefile, $frameParams, $handlerParams ) .
					"&nbsp;";
			}

		}

		return $iconhtml;

	}

	private function getIcons() {

		list( $hide_page_title_icon, $hide_category_title_icon ) =
			$this->queryHideTitleIcon();

		$pages = array();

		if ( !$hide_category_title_icon ) {
			$categories = $this->title->getParentCategories();
			foreach ( $categories as $category => $page ) {
				$pages[] = Title::newFromText( $category );
			}
		}

		if ( !$hide_page_title_icon ) {
			$pages[] = $this->title;
		}

		$icons = array();
		foreach ( $pages as $page ) {

			$discoveredIcons =
				$this->getPropertyValues( $page, $this->titleIconPropertyName );

			if ( $discoveredIcons ) {

				foreach ( $discoveredIcons as $icon ) {

					$found = false;
					foreach ( $icons as $foundIcon ) {

						if ( $foundIcon["icon"] === $icon ) {
							$found = true;
							break;
						}

					}

					if ( $found == false ) {
						$entry = array();
						$entry["page"] = $page;
						$entry["icon"] = $icon;
						$icons[] = $entry;
					}

				}

			}

		}

		return $icons;
	}

	private function queryHideTitleIcon() {

		$result = $this->getPropertyValues( $this->title,
			$this->hideTitleIconPropertyName );

		if ( count( $result ) > 0 ) {

			switch ( $result[0] ) {
			case "page":
				return array( true, false );
			case "category":
				return array( false, true );
			case "all":
				return array( true, true );
			}

		}

		return array( false, false );
	}

	private function getPropertyValues( Title $title, $propertyname ) {

		$store = \SMW\StoreFactory::getStore();

		// remove fragment
		$title = Title::newFromText( $title->getPrefixedText() );

		$subject = SMWDIWikiPage::newFromTitle( $title );
		$data = $store->getSemanticData( $subject );
		$property = SMWDIProperty::newFromUserLabel( $propertyname );
		$values = $data->getPropertyValues( $property );

		$strings = array();
		foreach ( $values as $value ) {
			if ( $value->getDIType() == SMWDataItem::TYPE_STRING ||
				$value->getDIType() == SMWDataItem::TYPE_BLOB ) {
				$strings[] = trim( $value->getString() );
			}
		}

		return $strings;
	}
}
