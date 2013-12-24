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
				$pageurl = $page->getLinkURL();
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

	private function getPageTitle($title) {
		global $TitleIcon_UseDisplayTitle;
		if ($TitleIcon_UseDisplayTitle) {
			$displaytitle = $this->queryPageDisplayTitle($title);
			if (strlen($displaytitle) != 0) {
				return $displaytitle;
			}
		}
		return $title->getPrefixedText();
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
			$this->queryHideTitleIcon($title);
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

	private function queryIconLinksOnPage($title) {
		global $TitleIcon_TitleIconPropertyName;
		return $this->getPropertyValues($title,
			$TitleIcon_TitleIconPropertyName);
	}

	private function queryPageDisplayTitle($title) {
		global $TitleIcon_DisplayTitlePropertyName;
		$result = $this->getPropertyValues($title,
			$TitleIcon_DisplayTitlePropertyName);
		if (count($result) > 0) {
			return $result[0];
		}
		return "";
	}

	private function queryHideTitleIcon($title) {
		global $TitleIcon_HideTitleIconPropertyName;
		$result = $this->getPropertyValues($title,
			$TitleIcon_HideTitleIconPropertyName);
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

	private function getPropertyValues($title, $propertyname) {
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
