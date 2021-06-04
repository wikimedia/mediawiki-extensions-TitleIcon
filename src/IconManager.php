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

namespace MediaWiki\Extension\TitleIcon;

use Config;
use Linker;
use Parser;
use RepoGroup;
use Title;

class IconManager {
	/** @var Config */
	private $config;

	/** @var Parser */
	private $parser;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var SMWInterface */
	private $smwInterface;

	/**
	 * @param Config $config
	 * @param Parser $parser
	 * @param RepoGroup $repoGroup
	 * @param SMWInterface $smwInterface
	 */
	public function __construct(
		Config $config,
		Parser $parser,
		RepoGroup $repoGroup,
		SMWInterface $smwInterface
	) {
		$this->config = $config;
		$this->parser = $parser;
		$this->repoGroup = $repoGroup;
		$this->smwInterface = $smwInterface;
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	public function getIconHTML( Title $title ) : string {
		$icons = $this->getIcons( $title );
		$iconhtml = '';
		foreach ( $icons as $icon ) {
			if ( $icon->getType() === Icon::ICON_TYPE_FILE ) {
				$iconhtml .= $this->getIconHTMLForFile( $icon->getIcon(), $icon->getPage() );
			}
		}
		return $iconhtml;
	}

	/**
	 * @param Title $title
	 * @return Icon[]
	 */
	public function getIcons( Title $title ) : array {
		[ $hide_page_title_icon, $hide_category_title_icon ] = $this->queryHideTitleIcon( $title );

		$pages = [];
		if ( !$hide_category_title_icon ) {
			$categories = $title->getParentCategories();
			foreach ( $categories as $category => $page ) {
				$pages[] = $category;
			}
		}

		if ( !$hide_page_title_icon ) {
			$pages[] = $title->getPrefixedText();
		}

		$icons = [];
		$this->getSMWIcons( $pages, $icons );

		return $icons;
	}

	/**
	 * @param Title $title
	 * @return bool[] containing two elements indicating for rendering this page if: [0] title
	 *                icons set on this page should be hidden and [1] title icons set on this
	 *                page's category pages should be hidden
	 */
	private function queryHideTitleIcon( Title $title ) : array {
		$result = $this->smwInterface->getPropertyValues(
			$title->getPrefixedText(),
			$this->config->get( 'TitleIcon_HideTitleIconPropertyName' )
		);

		if ( $result ) {
			switch ( $result[0] ) {
				case 'page':
					return [ true, false ];
				case 'category':
					return [ false, true ];
				case 'all':
					return [ true, true ];
			}
		}

		return [ false, false ];
	}

	/**
	 * @param string[] $pages
	 * @param Icon[] &$icons
	 */
	private function getSMWIcons( array $pages, array &$icons ) : void {
		foreach ( $pages as $page ) {
			$smwIcons = $this->smwInterface->getPropertyValues(
				$page,
				$this->config->get( 'TitleIcon_TitleIconPropertyName' )
			);

			foreach ( $smwIcons as $smwIcon ) {
				$found = false;
				foreach ( $icons as $icon ) {
					if ( $icon->getType() === Icon::ICON_TYPE_FILE && $icon->getIcon() === $smwIcon ) {
						$found = true;
						break;
					}
				}

				if ( $found == false ) {
					$icons[] = new Icon( $page, $smwIcon, Icon::ICON_TYPE_FILE );
				}
			}
		}
	}

	/**
	 * @param string $filename
	 * @param string $page
	 * @return string
	 */
	private function getIconHTMLForFile( string $filename, string $page ) : string {
		$filetitle = Title::newFromText( $filename, NS_FILE );
		$imagefile = $this->repoGroup->findFile( $filetitle );
		$title = Title::newFromText( $page );

		if ( $imagefile === false ) {
			return '';
		}

		if ( $this->config->get( 'TitleIcon_UseFileNameAsToolTip' ) ) {
			$tooltip = $filename;
			if ( strpos( $tooltip, '.' ) !== false ) {
				$tooltip = substr( $tooltip, 0, strpos( $tooltip, '.' ) );
			}
		} else {
			$tooltip = $title;
		}

		$frameParams = [
			'link-title' => $title,
			'alt' => $tooltip,
			'title' => $tooltip
		];

		$handlerParams = [
			'width' => '36',
			'height' => '36'
		];

		return Linker::makeImageLink(
			$this->parser,
			$filetitle,
			$imagefile,
			$frameParams,
			$handlerParams
			) . '&nbsp;';
	}
}
