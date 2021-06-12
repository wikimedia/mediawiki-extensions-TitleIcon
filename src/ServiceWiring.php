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

use ExtensionRegistry;
use MediaWiki\Json\JsonCodec;
use MediaWiki\MediaWikiServices;
use PageProps;

return [
	'TitleIcon:IconManager' => static function ( MediaWikiServices $services ) : IconManager {
		return new IconManager(
			$services->getMainConfig(),
			$services->getParser(),
			$services->getTitleParser(),
			$services->get( 'TitleIcon:PageProps' ),
			$services->getRepoGroup(),
			$services->getLinkRenderer(),
			$services->get( 'TitleIcon:JsonCodec' ),
			new SMWInterface( ExtensionRegistry::getInstance() )
		);
	},
	// TODO: remove when support for MW 1.35 is dropped
	'TitleIcon:PageProps' => static function ( MediaWikiServices $services ) : PageProps {
		if ( method_exists( $services, 'getPageProps' ) ) {
			return $services->getPageProps();
		}
		return PageProps::getInstance();
	},
	// TODO: remove when support for MW 1.35 is dropped
	'TitleIcon:JsonCodec' => static function ( MediaWikiServices $services ) : JsonCodec {
		if ( method_exists( $services, 'getJsonCodec' ) ) {
			return $services->getJsonCodec();
		}
		return new JsonCodec();
	}
];
