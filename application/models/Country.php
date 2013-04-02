<?php

class Model_Country extends Model_Campaign {
	protected function fetch($clause = null, $args = array()) {
		if ($clause) {
			$clause .= ' AND ';
		}
		$clause .= ' is_country = 1';
		return parent::fetch($clause, $args);
	}
}
