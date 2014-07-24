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

class TitleIcon {

	private static $m_already_invoked = false;

	public static function showIconInPageTitle(&$out, &$skin) {

		if (self::$m_already_invoked) {
			return true;
		}
		self::$m_already_invoked = true;

		$iconhtml = self::getIconHTML($skin->getTitle());

		if (strlen($iconhtml) > 0) {

			$iconhtml = strtr($iconhtml, array('"' => "'"));

			$prefix = Html::openElement('table', array(
				'style' => 'border:none;'
			));
			$prefix .= Html::openElement('tr');
			$prefix .= Html::openElement('td', array(
				'style' => 'border:none;'
			));

			$middle = Html::closeElement('td');
			$middle .= Html::openElement('td', array(
				'style' => 'border:none;'
			));

			$suffix = Html::closeElement('td');
			$suffix .= Html::closeElement('tr');
			$suffix .= Html::closeElement('table');

			$script =<<<END
jQuery(document).ready(function() {
	var title = jQuery('#firstHeading').html();
	jQuery('#firstHeading').html('$prefix' + "$iconhtml" + '$middle' + title + '$suffix');
});
END;
			$script = Html::inlineScript($script);
			$out->addScript($script);
		}

		return true;
	}

	public static function showIconInSearchTitle(&$title, &$text, $result,
		$terms, $page) {

		$pagelink = '[[' . $title->getPrefixedText() . ']]';
		$pagelink = $GLOBALS['wgParser']->parse($pagelink,
			$GLOBALS['wgTitle'], new ParserOptions())->getText();
		$pagelink = substr($pagelink, 3, strlen($pagelink) - 7);

		$text = Html::openElement('table', array(
			'style' => 'border:none;'
		));
		$text .= Html::openElement('tr');
		$text .= Html::openElement('td', array(
			'style' => 'vertical-align:top;border:none;'
		));
		$text .= self::getIconHTML($title);
		$text .= Html::closeElement('td');
		$text .= Html::openElement('td', array(
			'style' => 'vertical-align:top;border:none;'
		));
 		$text .= $pagelink;
		$text .= Html::closeElement('td');
		$text .= Html::closeElement('tr');
		$text .= Html::closeElement('table');

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
				$pageurl = $page->getLinkURL();
				if ($GLOBALS['TitleIcon_UseFileNameAsToolTip']) {
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
						'src' => $imageurl,
						$dimension => '36')) .
					Html::closeElement('a') . "&nbsp;";
			}
		}
		return $iconhtml;
	}

	private static function getIcons($title) {
		list($hide_page_title_icon, $hide_category_title_icon) =
			self::queryHideTitleIcon($title);
		$pages = array();
		if (!$hide_category_title_icon) {
			$categories = $title->getParentCategories();
			foreach ($categories as $category => $page) {
				$pages[] = Title::newFromText($category);
			}
		}
		if (!$hide_page_title_icon) {
			$pages[] = $title;
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

	private static function queryIconLinksOnPage($title) {
		return self::getPropertyValues($title,
			$GLOBALS['TitleIcon_TitleIconPropertyName']);
	}

	private static function queryHideTitleIcon($title) {
		$result = self::getPropertyValues($title,
			$GLOBALS['TitleIcon_HideTitleIconPropertyName']);
		if (count($result) > 0) {
			switch ($result[0]) {
			case "page":
				return array(true, false);
			case "category":
				return array(false, true);
			case "all":
				return array(true, true);
			}
		}
		return array(false, false);
	}

	private static function getPropertyValues($title, $propertyname) {
		$store = smwfGetStore();
		$subject = SMWDIWikiPage::newFromTitle($title);
		$data = $store->getSemanticData($subject);
		$property = SMWDIProperty::newFromUserLabel($propertyname);
		$values = $data->getPropertyValues($property);
		$strings = array();
		foreach ($values as $value) {
			if ($value->getDIType() == SMWDataItem::TYPE_STRING ||
				$value->getDIType() == SMWDataItem::TYPE_BLOB) {
				$strings[] = trim($value->getString());
			}
		}
		return $strings;
	}
}
