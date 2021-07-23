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

use InvalidArgumentException;
use MediaWiki\Json\JsonUnserializable;
use MediaWiki\Json\JsonUnserializableTrait;
use MediaWiki\Json\JsonUnserializer;
use MediaWiki\Linker\LinkTarget;
use Title;

class Icon implements JsonUnserializable {
	use JsonUnserializableTrait;

	public const ICON_TYPE_FILE = "file";
	public const ICON_TYPE_OOUI = "ooui";
	public const ICON_TYPE_UNICODE = "unicode";

	public const ICON_PROPERTY_NAME = 'titleicons';

	/** @var LinkTarget */
	private $source;

	/** @var string */
	private $icon;

	/** @var string */
	private $type;

	/** @var LinkTarget|null */
	private $link;

	/**
	 * @param LinkTarget $source
	 * @param string $icon
	 * @param string $type
	 * @param LinkTarget|null $link
	 */
	public function __construct( LinkTarget $source, string $icon, string $type, ?LinkTarget $link = null ) {
		$this->source = $source;
		if ( $type === self::ICON_TYPE_FILE ) {
			$this->icon = Title::newFromText( $icon, NS_FILE )->getPrefixedText();
		} else {
			$this->icon = $icon;
		}
		$this->type = $type;
		$this->link = $link;
	}

	/**
	 * @return LinkTarget
	 */
	public function getSource(): LinkTarget {
		return $this->source;
	}

	/**
	 * @return string
	 */
	public function getIcon(): string {
		return $this->icon;
	}

	/**
	 * @return string
	 */
	public function getType(): string {
		return $this->type;
	}

	/**
	 * @return LinkTarget|null
	 */
	public function getLink(): ?LinkTarget {
		return $this->link;
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	public static function isValidType( string $type ): bool {
		return in_array(
			$type,
			[
				self::ICON_TYPE_FILE,
				self::ICON_TYPE_OOUI,
				self::ICON_TYPE_UNICODE
			]
		);
	}

	/**
	 * @param JsonUnserializer $unserializer
	 * @param array $json
	 * @return Icon
	 */
	public static function newFromJsonArray( JsonUnserializer $unserializer, array $json ) {
		if ( !isset( $json['source-dbkey'] )
			|| !isset( $json['source-namespace'] )
			|| !isset( $json['icon'] )
			|| !isset( $json['type'] )
			) {
			throw new InvalidArgumentException( "Missing Icon field(s)" );
		}
		if ( isset( $json['link-dbkey'] ) && isset( $json['link-namespace'] ) ) {
			$link = Title::newFromText( $json['link-dbkey'], $json['link-namespace'] );
		} else {
			$link = null;
		}
		return new Icon(
			Title::newFromText( $json['source-dbkey'], $json['source-namespace'] ),
			$json['icon'],
			$json['type'],
			$link
		);
	}

	/**
	 * @return array
	 */
	protected function toJsonArray(): array {
		$result = [
			'source-dbkey' => $this->source->getDBkey(),
			'source-namespace' => $this->source->getNamespace(),
			'icon' => $this->icon,
			'type' => $this->type
			];
		if ( $this->link ) {
			$result['link-dbkey'] = $this->link->getDBkey();
			$result['link-namespace'] = $this->link->getNamespace();
		}
		return $result;
	}
}
