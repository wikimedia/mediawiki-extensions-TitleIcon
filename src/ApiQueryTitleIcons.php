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

use ApiQuery;
use ApiQueryBase;
use MediaWiki\Json\JsonCodec;

/**
 * A query module to list all title icons found on a given set of pages.
 *
 * @ingroup API
 */
class ApiQueryTitleIcons extends ApiQueryBase {
	/**
	 * @var IconManager
	 */
	private $iconManager;

	/**
	 * @var JsonCodec
	 */
	private $jsonCodec;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param IconManager $iconManager
	 * @param JsonCodec $jsonCodec
	 */
	public function __construct(
		ApiQuery $query,
		string $moduleName,
		IconManager $iconManager,
		JsonCodec $jsonCodec
	) {
		parent::__construct( $query, $moduleName );
		$this->iconManager = $iconManager;
		$this->jsonCodec = $jsonCodec;
	}

	public function execute(): void {
		$titles = $this->getPageSet()->getGoodPages();
		if ( $titles === [] ) {
			return;
		}

		$result = $this->getResult();

		foreach ( $titles as $id => $title ) {
			$result->addValue(
				[ 'query', 'pages', $id ],
				'titleicons',
				$this->jsonCodec->serialize( $this->iconManager->getIcons( $title ) )
			);
		}
	}

	/**
	 * @return string[]
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=query&prop=titleicons&titles=Main%20Page'
				=> 'apihelp-query+titleicons-example',
		];
	}

	/**
	 * @return string
	 */
	public function getHelpUrls(): string {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:TitleIcon';
	}
}
