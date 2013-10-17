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

/**
* To activate the functionality of this extension include the following
* in your LocalSettings.php file:
* include_once("$IP/extensions/TitleIcon/TitleIcon.php");
*/

$wgExtensionCredits['parserhook'][] = array (
	'name' => 'Title Icon',
	'version' => '1.0',
	'author' => 'Cindy Cicalese',
	'descriptionmsg' => 'titleicon-desc',
);

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
		global $wgTitle;
		$iconhtml = self::getIconHTML($wgTitle);
		if (strlen($iconhtml) > 0) {
			$text = $iconhtml . self::getPageTitle($wgTitle);
			$script =<<<END
jQuery(document).ready(function() {
	jQuery('#firstHeading').html("$text");
});
END;
			$script = '<script type="text/javascript">' . $script . "</script>";
			global $wgOut;
			$wgOut->addScript($script);
		}
		return true;
	}

	static function showIconInSearchTitle(&$title, &$text, $result, $terms,
		$page) {
		$text = self::getIconHTML($title) . self::getPageLink($title);
		return true;
	}

	private static function getIconHTML($title) {
		$icons = self::getIcons($title);
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
					$dimension = "height='36'";
				} else {
					$dimension = "width='36'";
				}
				$iconhtml .= "<a href='" . $pageurl .
					"' title = '" . $tooltip . "'><img alt='" . $tooltip .
					"' src='" .  $imageurl . "'" . $dimension .
					" /></a>&nbsp;";
			}
		}
		return $iconhtml;
	}

	private static function getPageTitle($pagetitle) {
		$title = $pagetitle->getPrefixedText();
		global $TitleIcon_UseDisplayTitle;
		if ($TitleIcon_UseDisplayTitle) {
			$displaytitle = self::queryPageDisplayTitle($title);
			if (strlen($displaytitle) != 0) {
				$title = $displaytitle;
			}
		}
		return $title;
	}

	private static function getPageLink($pagetitle) {
		$pageurl = $pagetitle->getLinkURL();
		$title = self::getPageTitle($pagetitle);
		$pagelink = "<a href='" . $pageurl .	"' title = '" . $title . "'>" .
			$title . "</a>&nbsp;";
		return $pagelink;
	}

	private static function getIcons($title) {
		list($hide_page_title_icon, $hide_category_title_icon) =
			self::queryHideTitleIcon($title->getPrefixedText());
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
			$discoveredIcons = self::queryIconLinksOnPage($page);
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

	private static function queryIconLinksOnPage($page) {
		global $TitleIcon_TitleIconPropertyName;
		$query = '[[:' . $page . ']]';
		$result = self::ask($query, $TitleIcon_TitleIconPropertyName, 5);
		return array_map('trim', explode(",", $result));
	}

	private static function queryPageDisplayTitle($page) {
		global $TitleIcon_DisplayTitlePropertyName;
		$query = '[[:' . $page . ']]';
		$result = self::ask($query, $TitleIcon_DisplayTitlePropertyName, 1);
		return $result;
	}

	private static function queryHideTitleIcon($page) {
		global $TitleIcon_HideTitleIconPropertyName;
		$query = '[[:' . $page . ']]';
		$result = self::ask($query, $TitleIcon_HideTitleIconPropertyName, 1);
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

	private static function ask($query, $property, $limit) {
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
