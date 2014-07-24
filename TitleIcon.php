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

if (version_compare($GLOBALS['wgVersion'], '1.21', 'lt')) {
	die('<b>Error:</b> This version of TitleIcon is only compatible with MediaWiki 1.21 or above.');
}

$GLOBALS['wgExtensionCredits']['semantic'][] = array (
	'name' => 'Title Icon',
	'version' => '2.0',
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

$GLOBALS['wgAutoloadClasses']['TitleIcon'] = __DIR__ . '/TitleIcon.class.php';

$GLOBALS['wgMessagesDirs']['TitleIcon'] = __DIR__ . '/i18n';
$GLOBALS['wgExtensionMessagesFiles']['TitleIcon'] =
	__DIR__ . '/TitleIcon.i18n.php';

$GLOBALS['wgHooks']['ParserFirstCallInit'][] = 'efTitleIconSetup';

function efTitleIconSetup (& $parser) {

	if (!isset($GLOBALS['TitleIcon_EnableIconInPageTitle'])) {
		$GLOBALS['TitleIcon_EnableIconInPageTitle'] = true;
	}

	if (!isset($GLOBALS['TitleIcon_EnableIconInSearchTitle'])) {
		$GLOBALS['TitleIcon_EnableIconInSearchTitle'] = true;
	}

	if (!isset($GLOBALS['TitleIcon_UseFileNameAsToolTip'])) {
		$GLOBALS['TitleIcon_UseFileNameAsToolTip'] = true;
	}

	if (!isset($GLOBALS['TitleIcon_TitleIconPropertyName'])) {
		$GLOBALS['TitleIcon_TitleIconPropertyName'] = "Title Icon";
	}

	if (!isset($GLOBALS['TitleIcon_HideTitleIconPropertyName'])) {
		$GLOBALS['TitleIcon_HideTitleIconPropertyName'] = "Hide Title Icon";
	}

	if ($GLOBALS['TitleIcon_EnableIconInPageTitle']) {
		$GLOBALS['wgHooks']['BeforePageDisplay'][] =
			'TitleIcon::showIconInPageTitle';
	}

	if ($GLOBALS['TitleIcon_EnableIconInSearchTitle']) {
		$GLOBALS['wgHooks']['ShowSearchHitTitle'][] =
			'TitleIcon::showIconInSearchTitle';
	}

	return true;
}
