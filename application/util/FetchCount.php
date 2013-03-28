<?php

class Util_FetchCount {
	public $fetched = 0;
	public $added = 0;
	public $type = '';

	public function __construct($fetched, $added, $type='tweet') {
		$this->type = $type;
		$this->fetched = $fetched;
		$this->added = $added;
	}

	public function add($count) {
		$this->fetched += $count->fetched;
		$this->added += $count->added;
	}

	public function __toString() {
		$items = $this->type . ($this->fetched == 1 ? '' : 's');
		return "Fetched $this->fetched $items, added $this->added";
	}
}

