<?php

namespace SMW;

use SMWDataItem;

class DIWikiPage extends SMWDataItem {
	/**
	 * @param string $text
	 * @param int $namespace
	 * @return DIWikiPage
	 */
	public static function newFromText( $text, $namespace = NS_MAIN ): self {
		return new self();
	}
}
