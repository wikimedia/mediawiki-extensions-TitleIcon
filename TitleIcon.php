<?php

/*
 * Copyright (c) 2013 The MITRE Corporation
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

if (!defined('MEDIAWIKI')) {
	die('<b>Error:</b> This file is part of a MediaWiki extension and cannot be run standalone.');
}

if (version_compare($wgVersion, '1.21', 'lt')) {
	die('<b>Error:</b> This version of TitleIcon is only compatible with MediaWiki 1.21 or above.');
}

$wgExtensionCredits['semantic'][] = array (
	'name' => 'Title Icon',
	'version' => '1.0',
	'author' => array(
		'[https://www.mediawiki.org/wiki/User:Cindy.cicalese Cindy Cicalese]'
	),
	'descriptionmsg' => 'titleicon-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Title_Icon',
);

// Special thanks to
// [https://www.mediawiki.org/wiki/User:Bernadette Bernadette Clemente]
// for the original idea that inspired this extension and to Keven Ring
// for an early implementation of this extension.

$wgExtensionMessagesFiles['TitleIcon'] = __DIR__ . '/TitleIcon.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'efTitleIconSetup';

$TitleIcon_EnableIconInPageTitle = true;
$TitleIcon_EnableIconInSearchTitle = true;
$TitleIcon_UseFileNameAsToolTip = true;
$TitleIcon_TitleIconPropertyName = "Title Icon";
$TitleIcon_HideTitleIconPropertyName = "Hide Title Icon";
$TitleIcon_UseDisplayTitle = false;
$TitleIcon_DisplayTitlePropertyName = "Display Title";

function efTitleIconSetup (& $parser) {
	global $TitleIcon_EnableIconInPageTitle, $TitleIcon_EnableIconInSearchTitle,
		$wgHooks;
	if ($TitleIcon_EnableIconInPageTitle) {
		$wgHooks['BeforePageDisplay'][] = 'TitleIcon::showIconInPageTitle';
	}
	if ($TitleIcon_EnableIconInSearchTitle) {
		$wgHooks['ShowSearchHitTitle'][] = 'TitleIcon::showIconInSearchTitle';
	}
	return true;
}

class TitleIcon {

	private static $m_already_invoked = false;

	static function showIconInPageTitle(&$out, &$skin) {
		if (self::$m_already_invoked) {
			return true;
		}
		self::$m_already_invoked = true;
		$instance = new TitleIcon;
		$instance->instanceShowIconInPageTitle($out, $skin);
		return true;
	}

	static function showIconInSearchTitle(&$title, &$text, $result, $terms,
		$page) {
		$instance = new TitleIcon;
		$text = $instance->getIconHTML($title) . $instance->getPageLink($title);
		return true;
	}

	private function instanceShowIconInPageTitle($out, $skin) {
		$iconhtml = $this->getIconHTML($skin->getTitle());
		if (strlen($iconhtml) > 0) {
			$iconhtml = strtr($iconhtml, array('"' => "'"));
			global $TitleIcon_UseDisplayTitle;
			if ($TitleIcon_UseDisplayTitle) {
				$title = $this->getPageTitle($skin->getTitle());
				$script =<<<END
jQuery(document).ready(function() {
	jQuery('#firstHeading').html("$iconhtml" + "$title");
});
END;
			} else {
				$script =<<<END
jQuery(document).ready(function() {
	var title = jQuery('#firstHeading').html();
	jQuery('#firstHeading').html("$iconhtml" + title);
});
END;
			}
			$script = Html::inlineScript($script);
			$out->addScript($script);
		}
	}

	private function getIconHTML($title) {
		$icons = $this->getIcons($title);
		$iconhtml = "";
		foreach ($icons as $iconinfo) {
			$page = $iconinfo["page"];
			$icon = $iconinfo["icon"];
			$imagefile = wfFindFile(Title::newFromText("File:" . $icon));
			if ($imagefile !== false) {
				$imageurl = $imagefile->getURL();
				$pagetitle = Title::newFromText($page);
				$pageurl = $pagetitle->getLinkURL();
				global $TitleIcon_UseFileNameAsToolTip;
				if ($TitleIcon_UseFileNameAsToolTip) {
					$tooltip = $icon;
					if (strpos($tooltip, '.' ) !==	false) {
						$tooltip = substr($tooltip, 0, strpos($tooltip, '.'));
					}
				} else {
					$tooltip = $page;
				}
				$width = $imagefile->getWidth();
				$height = $imagefile->getHeight();
				if ($width < $height) {
					$dimension = 'height';
				} else {
					$dimension = 'width';
				}
				$iconhtml .= Html::openElement('a',
					array(
						'href' => $pageurl,
						'title' => $tooltip)) .
					Html::element('img', array(
						'alt' => $tooltip,
						'src' =>  $imageurl,
						$dimension => '36')) .
					Html::closeElement('a') . "&nbsp;";
			}
		}
		return $iconhtml;
	}

	private function getPageTitle($pagetitle) {
		$title = $pagetitle->getPrefixedText();
		global $TitleIcon_UseDisplayTitle;
		if ($TitleIcon_UseDisplayTitle) {
			$displaytitle = $this->queryPageDisplayTitle($title);
			if (strlen($displaytitle) != 0) {
				$title = $displaytitle;
			}
		}
		return $title;
	}

	private function getPageLink($pagetitle) {
		$pageurl = $pagetitle->getLinkURL();
		$title = $this->getPageTitle($pagetitle);
		$pagelink = Html::element('a', array('href' => $pageurl,
			'title' => $title), $title) . '&nbsp;';
		return $pagelink;
	}

	private function getIcons($title) {
		list($hide_page_title_icon, $hide_category_title_icon) =
			$this->queryHideTitleIcon($title->getPrefixedText());
		$pages = array();
		if (!$hide_category_title_icon) {
			$categories = $title->getParentCategories();
			foreach ($categories as $category => $page) {
				$pages[] = $category;
			}
		}
		if (!$hide_page_title_icon) {
			$pages[] = $title->getPrefixedText();
		}
		$icons = array();
		foreach ($pages as $page) {
			$discoveredIcons = $this->queryIconLinksOnPage($page);
			if ($discoveredIcons) {
				foreach ($discoveredIcons as $icon) {
					$found = false;
					foreach ($icons as $foundIcon) {
						if ($foundIcon["icon"] === $icon) {
							$found = true;
							break;
						}
					}
					if ($found == false) {
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

	private function queryIconLinksOnPage($page) {
		global $TitleIcon_TitleIconPropertyName;
		$query = '[[:' . $page . ']]';
		$result = $this->ask($query, $TitleIcon_TitleIconPropertyName, 5);
		return array_map('trim', explode(",", $result));
	}

	private function queryPageDisplayTitle($page) {
		global $TitleIcon_DisplayTitlePropertyName;
		$query = '[[:' . $page . ']]';
		$result = $this->ask($query, $TitleIcon_DisplayTitlePropertyName, 1);
		return $result;
	}

	private function queryHideTitleIcon($page) {
		global $TitleIcon_HideTitleIconPropertyName;
		$query = '[[:' . $page . ']]';
		$result = $this->ask($query, $TitleIcon_HideTitleIconPropertyName, 1);
		switch ($result) {
		case "page":
			return array(true, false);
		case "category":
			return array(false, true);
		case "all":
			return array(true, true);
		}
		return array(false, false);
	}

	private function ask($query, $property, $limit) {
		$params = array();
		$params[] = $query;
		$params[] = "?" . $property;
		$params[] = "format=list";
		$params[] = "mainlabel=-";
		$params[] = "headers=hide";
		$params[] = "searchlabel=";
		$params[] = "limit=" . $limit;
		$result = SMWQueryProcessor::getResultFromFunctionParams($params,
			SMW_OUTPUT_WIKI);
		$result = trim($result);
		return $result;
	}
}
