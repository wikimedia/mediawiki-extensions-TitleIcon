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
use Language;
use Linker;
use MediaWiki\Json\JsonCodec;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use Message;
use PageProps;
use Parser;
use RepoGroup;
use Title;
use TitleParser;

class IconManager {
	/** @var Config */
	private $config;

	/** @var Parser */
	private $parser;

	/** @var TitleParser */
	private $titleParser;

	/** @var Language */
	private $contentLanguage;

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

	/** @var bool */
	private $hideNamespaceIcons = false;

	/**
	 * @param Config $config
	 * @param Parser $parser
	 * @param TitleParser $titleParser
	 * @param Language $contentLangauge
	 * @param PageProps $pageProps
	 * @param RepoGroup $repoGroup
	 * @param LinkRenderer $linkRenderer
	 * @param JsonCodec $jsonCodec
	 * @param SMWInterface $smwInterface
	 */
	public function __construct(
		Config $config,
		Parser $parser,
		TitleParser $titleParser,
		Language $contentLangauge,
		PageProps $pageProps,
		RepoGroup $repoGroup,
		LinkRenderer $linkRenderer,
		JsonCodec $jsonCodec,
		SMWInterface $smwInterface
	) {
		$this->config = $config;
		$this->parser = $parser;
		$this->titleParser = $titleParser;
		$this->contentLanguage = $contentLangauge;
		$this->pageProps = $pageProps;
		$this->repoGroup = $repoGroup;
		$this->linkRenderer = $linkRenderer;
		$this->jsonCodec = $jsonCodec;
		$this->smwInterface = $smwInterface;
		$this->icons = [];
	}

	/**
	 * @param LinkTarget $linkTarget
	 * @return LinkTarget[]
	 */
	private function getCategories( LinkTarget $linkTarget ): array {
		$result = [];
		$categories = Title::newFromLinkTarget( $linkTarget )->getParentCategories();
		foreach ( $categories as $category => $page ) {
			$result[] = $this->titleParser->parseTitle( $category );
		}
		return $result;
	}

	/**
	 * @param LinkTarget $linkTarget
	 * @return Icon[]
	 */
	public function getIcons( LinkTarget $linkTarget ): array {
		$key = self::getKeyForPage( $linkTarget );
		if ( !isset( $this->icons[$key] ) ) {
			$this->icons[$key] = [];
		}

		/** @var LinkTarget[] $queryTargets */
		$queryTargets = [];

		$this->queryHideTitleIcon( $linkTarget );

		if ( !$this->hidePageIcons ) {
			$queryTargets[] = $linkTarget;
			$this->queryPagePropsIcons( $linkTarget );
		} else {
			$this->icons[$key] = [];
		}

		if ( !$this->hideCategoryIcons ) {
			$categories = $this->getCategories( $linkTarget );
			foreach ( $categories as $category ) {
				$queryTargets[] = $category;
				$this->queryPagePropsIcons( $category );
				$categoryKey = self::getKeyForPage( $category );
				if ( isset( $this->icons[$categoryKey] ) ) {
					foreach ( $this->icons[$categoryKey] as $icon ) {
						$this->addIcon( $linkTarget, $icon );
					}
				}
			}
		}

		if ( !$this->hideNamespaceIcons ) {
			$namespaceText = $this->contentLanguage->getNsText( $linkTarget->getNamespace() );
			if ( $namespaceText === '' ) {
				$namespaceText = Message::newFromKey( 'Blanknamespace' )->plain();
			}
			$namespacePage = $this->titleParser->parseTitle( $namespaceText, NS_PROJECT );
			$queryTargets[] = $namespacePage;
			$this->queryPagePropsIcons( $namespacePage );
			$namespaceKey = self::getKeyForPage( $namespacePage );
			if ( isset( $this->icons[$namespaceKey] ) ) {
				foreach ( $this->icons[$namespaceKey] as $icon ) {
					$this->addIcon( $linkTarget, $icon );
				}
			}
		}

		foreach ( $queryTargets as $queryTarget ) {
			$smwIcons = $this->smwInterface->getPropertyValues(
				$queryTarget,
				$this->config->get( 'TitleIcon_TitleIconPropertyName' )
			);
			foreach ( $smwIcons as $smwIcon ) {
				$this->addIcon(
					$linkTarget,
					new Icon( $queryTarget, $smwIcon, Icon::ICON_TYPE_FILE )
				);
			}
		}

		return $this->icons[$key];
	}

	/**
	 * @param LinkTarget $linkTarget
	 */
	private function queryPagePropsIcons( LinkTarget $linkTarget ): void {
		$title = Title::newFromLinkTarget( $linkTarget );
		$icons = $this->pageProps->getProperties( $title, Icon::ICON_PROPERTY_NAME );
		$pageId = $title->getArticleId();
		if ( $icons && isset( $icons[$pageId] ) ) {
			$icons = $this->jsonCodec->unserialize( $icons[$pageId] );
			if ( $icons ) {
				foreach ( $icons as $icon ) {
					$icon = $this->jsonCodec->unserialize( $icon );
					if ( $icon ) {
						$this->addIcon( $linkTarget, $icon );
					}
				}
			}
		}
	}

	/**
	 * @param LinkTarget $source
	 * @param string $type
	 * @param string|null $icon
	 * @param LinkTarget|null $link
	 */
	public function parseIcons( LinkTarget $source, string $type, ?string $icon, ?LinkTarget $link ) {
		if ( !$icon ) {
			return;
		}

		$this->addIcon( $source, new Icon( $source, $icon, $type, $link ) );
	}

	/**
	 * @param Parser $parser
	 */
	public function saveIcons( Parser $parser ): void {
		$key = self::getKeyForPage( $parser->getTitle() );
		if ( isset( $this->icons[$key] ) ) {
			$parser->getOutput()->setProperty(
				Icon::ICON_PROPERTY_NAME,
				$this->jsonCodec->serialize( $this->icons[$key] )
			);
		}
	}

	/**
	 * @param LinkTarget $source
	 * @param Icon $icon
	 */
	private function addIcon( LinkTarget $source, Icon $icon ): void {
		$key = self::getKeyForPage( $source );
		if ( !isset( $this->icons[$key] ) ) {
			$this->icons[$key] = [];
		}
		foreach ( $this->icons[$key] as $savedIcon ) {
			if ( $icon->getType() === $savedIcon->getType() && $icon->getIcon() === $savedIcon->getIcon() ) {
				return;
			}
		}
		$this->icons[$key][] = $icon;
	}

	/**
	 * @param LinkTarget $source
	 * @return string
	 */
	public function getHTML( LinkTarget $source ): string {
		$key = self::getKeyForPage( $source );
		if ( !isset( $this->icons[$key] ) ) {
			return '';
		}
		$icons = $this->icons[$key];
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
	public function hideTitleIcon( ?string $flag ): void {
		switch ( $flag ) {
			case 'page':
				$this->hidePageIcons = true;
				break;
			case 'category':
				$this->hideCategoryIcons = true;
				break;
			case 'namespace':
				$this->hideNamespaceIcons = true;
				break;
			case 'all':
				$this->hidePageIcons = true;
				$this->hideCategoryIcons = true;
				$this->hideNamespaceIcons = true;
		}
	}

	/**
	 * @param LinkTarget $linkTarget
	 */
	private function queryHideTitleIcon( LinkTarget $linkTarget ): void {
		$results = $this->smwInterface->getPropertyValues(
			$linkTarget,
			$this->config->get( 'TitleIcon_HideTitleIconPropertyName' )
		);
		foreach ( $results as $result ) {
			$this->hideTitleIcon( $result );
		}
	}

	/**
	 * @param Icon $icon
	 * @return string
	 */
	private function getFileIconHTML( Icon $icon ): string {
		$filename = $icon->getIcon();
		$filetitle = Title::newFromText( $filename, NS_FILE );
		$imagefile = $this->repoGroup->findFile( $filetitle );

		if ( $imagefile === false ) {
			return '';
		}

		if ( $this->config->get( 'TitleIcon_UseFileNameAsToolTip' ) ) {
			$tooltip = $filename;
			if ( strpos( $tooltip, '.' ) !== false ) {
				$tooltip = substr( $tooltip, 0, strpos( $tooltip, '.' ) );
			}
		} else {
			$tooltip = $icon->getSource();
		}

		$linkTarget = $icon->getLink();
		if ( !$linkTarget ) {
			$linkTarget = $icon->getSource();
		}

		$frameParams = [
			'link-title' => $linkTarget,
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
	private function getOOUIIconHTML( Icon $icon ): string {
		$linkTitle = $icon->getLink();
		if ( !$linkTitle ) {
			$linkTitle = $icon->getSource();
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
	private function getUnicodeIconHTML( Icon $icon ): string {
		$linkTitle = $icon->getLink();
		if ( !$linkTitle ) {
			$linkTitle = $icon->getSource();
		}
		return $this->linkRenderer->makeLink(
			$linkTitle,
			new HtmlArmor( $icon->getIcon() )
		) . "&nbsp;";
	}

	/**
	 * @param LinkTarget $linkTarget
	 * @return string
	 */
	public static function getKeyForPage( LinkTarget $linkTarget ): string {
		return 'ns' . $linkTarget->getNamespace() . ':' . $linkTarget->getDBkey();
	}
}
