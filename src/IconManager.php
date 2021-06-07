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
use HtmlArmor;
use Linker;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Linker\LinkRenderer;
use PageProps;
use Parser;
use RepoGroup;
use Title;

class IconManager {
	/** @var Config */
	private $config;

	/** @var Parser */
	private $parser;

	/** @var PageProps */
	private $pageProps;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var JsonCodec */
	private $jsonCodec;

	/** @var SMWInterface */
	private $smwInterface;

	/** @var Icon[][] array indexed by page name of title icons that are defined on that page */
	private $icons;

	/** @var bool */
	private $hidePageIcons = false;

	/** @var bool */
	private $hideCategoryIcons = false;

	/**
	 * @param Config $config
	 * @param Parser $parser
	 * @param PageProps $pageProps
	 * @param RepoGroup $repoGroup
	 * @param LinkRenderer $linkRenderer
	 * @param JsonCodec $jsonCodec
	 * @param SMWInterface $smwInterface
	 */
	public function __construct(
		Config $config,
		Parser $parser,
		PageProps $pageProps,
		RepoGroup $repoGroup,
		LinkRenderer $linkRenderer,
		JsonCodec $jsonCodec,
		SMWInterface $smwInterface
	) {
		$this->config = $config;
		$this->parser = $parser;
		$this->pageProps = $pageProps;
		$this->repoGroup = $repoGroup;
		$this->linkRenderer = $linkRenderer;
		$this->jsonCodec = $jsonCodec;
		$this->smwInterface = $smwInterface;
		$this->icons = [];
	}

	/**
	 * @param Title $title
	 * @return Title[]
	 */
	public function getCategories( Title $title ) {
		$result = [];
		$categories = $title->getParentCategories();
		foreach ( $categories as $category => $page ) {
			$result[] = Title::newFromText( $category );
		}
		return $result;
	}

	/**
	 * @param Title $title
	 * @param Title[] $categories
	 * @param bool $queryPageProps
	 * @return Icon[]
	 */
	public function getIcons( Title $title, array $categories, bool $queryPageProps ) : array {
		$this->queryHideTitleIcon( $title );

		$page = $title->getPrefixedText();
		$queryPages = [];

		if ( $this->hidePageIcons ) {
			$this->icons[$page] = [];
		} else {
			$queryPages[] = $page;
			if ( $queryPageProps ) {
				$this->queryPagePropsIcons( $title );
			}
		}

		if ( !$this->hideCategoryIcons ) {
			foreach ( $categories as $category ) {
				$categoryPage = $category->getPrefixedText();
				$queryPages[] = $categoryPage;
				$this->queryPagePropsIcons( $category );
				if ( isset( $this->icons[$categoryPage] ) ) {
					foreach ( $this->icons[$categoryPage] as $icon ) {
						$this->addIcon( $page, $icon );
					}
				}
			}
		}

		foreach ( $queryPages as $queryPage ) {
			$smwIcons = $this->smwInterface->getPropertyValues(
				$queryPage,
				$this->config->get( 'TitleIcon_TitleIconPropertyName' )
			);
			foreach ( $smwIcons as $smwIcon ) {
				$this->addIcon(
					$page,
					new Icon( $queryPage, $smwIcon, Icon::ICON_TYPE_FILE )
				);
			}
		}

		return $this->icons[$page] ?? [];
	}

	/**
	 * @param Title $title
	 */
	private function queryPagePropsIcons( Title $title ) : void {
		$icons = $this->pageProps->getProperties( $title, Icon::ICON_PROPERTY_NAME );
		$page = $title->getPrefixedText();
		$pageId = $title->getId();
		if ( $icons && isset( $icons[$pageId] ) ) {
			$icons = $this->jsonCodec->unserialize( $icons[$pageId] );
			foreach ( $icons as $icon ) {
				$icon = $this->jsonCodec->unserialize( $icon );
				if ( $icon ) {
					$this->addIcon( $page, $icon );
				}
			}
		}
	}

	/**
	 * @param string $page
	 * @param string $type
	 * @param string|null $icon
	 * @param string|null $link
	 */
	public function parseIcons( string $page, string $type, ?string $icon, ?string $link ) {
		if ( !$icon ) {
			return;
		}

		$this->addIcon( $page, new Icon( $page, $icon, $type, $link ) );

		$this->parser->getOutput()->setProperty(
			Icon::ICON_PROPERTY_NAME,
			$this->jsonCodec->serialize( $this->icons[$page] )
		);
	}

	/**
	 * @param string $page
	 * @param Icon $icon
	 */
	public function addIcon( string $page, Icon $icon ) : void {
		if ( !isset( $this->icons[$page] ) ) {
			$this->icons[$page] = [];
		}
		foreach ( $this->icons[$page] as $savedIcon ) {
			if ( $icon->getType() === $savedIcon->getType() && $icon->getIcon() === $savedIcon->getIcon() ) {
				return;
			}
		}
		$this->icons[$page][] = $icon;
	}

	/**
	 * @param string $page
	 * @return string
	 */
	public function getHTML( string $page ) : string {
		if ( !isset( $this->icons[$page] ) ) {
			return '';
		}
		$icons = $this->icons[$page];
		$iconhtml = '';
		foreach ( $icons as $icon ) {
			switch ( $icon->getType() ) {
				case Icon::ICON_TYPE_FILE:
					$iconhtml .= $this->getFileIconHTML( $icon );
					break;
				case Icon::ICON_TYPE_OOUI:
					$iconhtml .= $this->getOOUIIconHTML( $icon );
					break;
				case Icon::ICON_TYPE_UNICODE:
					$iconhtml .= $this->getUnicodeIconHTML( $icon );
			}
		}
		return $iconhtml;
	}

	/**
	 * @param string|null $flag 'page', 'category', or 'all'
	 */
	public function hideTitleIcon( ?string $flag ) :void {
		switch ( $flag ) {
			case 'page':
				$this->hidePageIcons = true;
				break;
			case 'category':
				$this->hideCategoryIcons = true;
				break;
			case 'all':
				$this->hidePageIcons = true;
				$this->hideCategoryIcons = true;
		}
	}

	/**
	 * @param Title $title
	 */
	private function queryHideTitleIcon( Title $title ) : void {
		$result = $this->smwInterface->getPropertyValues(
			$title,
			$this->config->get( 'TitleIcon_HideTitleIconPropertyName' )
		);
		if ( isset( $result[0] ) ) {
			$this->hideTitleIcon( $result[0] );
		}
	}

	/**
	 * @param Icon $icon
	 * @return string
	 */
	private function getFileIconHTML( Icon $icon ) : string {
		$filename = $icon->getIcon();
		$filetitle = Title::newFromText( $filename, NS_FILE );
		$imagefile = $this->repoGroup->findFile( $filetitle );

		$title = Title::newFromText( $icon->getPage() );

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

		if ( $icon->getLink() ) {
			$linkTitle = Title::newFromText( $icon->getLink() );
			if ( !$linkTitle ) {
				$linkTitle = $title;
			}
		} else {
			$linkTitle = $title;
		}

		$frameParams = [
			'link-title' => $linkTitle,
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

	/**
	 * @param Icon $icon
	 * @return string
	 */
	private function getOOUIIconHTML( Icon $icon ) : string {
		if ( $icon->getLink() ) {
			$linkTitle = Title::newFromText( $icon->getLink() );
		} else {
			$linkTitle = Title::newFromText( $icon->getPage() );
		}
		if ( !$linkTitle ) {
			return '';
		}
		$url = 'resources/lib/ooui/themes/wikimediaui/images/icons/' . $icon->getIcon();
		return $this->linkRenderer->makeLink(
				$linkTitle,
				new HtmlArmor( Linker::makeExternalImage( $url ) )
			) . "&nbsp;";
	}

	/**
	 * @param Icon $icon
	 * @return string
	 */
	private function getUnicodeIconHTML( Icon $icon ) : string {
		if ( $icon->getLink() ) {
			$linkTitle = Title::newFromText( $icon->getLink() );
		} else {
			$linkTitle = Title::newFromText( $icon->getPage() );
		}
		if ( !$linkTitle ) {
			return '';
		}
		return $this->linkRenderer->makeLink( $linkTitle, new HtmlArmor( $icon->getIcon() ) ) . "&nbsp;";
	}
}
