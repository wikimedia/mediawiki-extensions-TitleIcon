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
use MediaWiki\Linker\LinkTarget;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\StoreFactory;
use SMWDataItem;

class SMWInterface {
	/** @var bool */
	private $isLoaded;

	/**
	 * @param ExtensionRegistry $extensionRegistry
	 */
	public function __construct(
		ExtensionRegistry $extensionRegistry
	) {
		$this->isLoaded = $extensionRegistry->isLoaded( 'SemanticMediaWiki' );
	}

	/**
	 * @return bool
	 */
	public function isLoaded(): bool {
		return $this->isLoaded;
	}

	/**
	 * @param LinkTarget $linkTarget
	 * @param string $propertyName
	 * @return string[]
	 */
	public function getPropertyValues( LinkTarget $linkTarget, string $propertyName ): array {
		if ( !$this->isLoaded ) {
			return [];
		}

		$store = StoreFactory::getStore();

		$subject = DIWikiPage::newFromText( $linkTarget->getDBkey(), $linkTarget->getNamespace() );
		$data = $store->getSemanticData( $subject );
		$property = DIProperty::newFromUserLabel( $propertyName );
		$values = $data->getPropertyValues( $property );

		$strings = [];
		foreach ( $values as $value ) {
			if ( $value->getDIType() == SMWDataItem::TYPE_BLOB ) {
				$strings[] = trim( $value->getString() );
			}
		}

		return $strings;
	}
}
