<?php

class SMWDataItem {
	public const TYPE_STRING = 0;
	public const TYPE_BLOB = 0;
	public const TYPE_WIKIPAGE = 0;

	/**
	 * @var int
	 */
	private $type = 0;

	/**
	 * @var Title
	 */
	private $title = null;

	/**
	 * @return int
	 */
	public function getDIType() : int {
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getString() : string {
		return '';
	}

	/**
	 * @return Title
	 */
	public function getTitle() : Title {
		return $this->title;
	}
}
