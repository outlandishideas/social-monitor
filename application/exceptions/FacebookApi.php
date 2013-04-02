<?php

class Exception_FacebookApi extends RuntimeException {

	protected $fql;
	protected $errors;

	/**
	 * @param string $msg
	 * @param int $code
	 * @param string $fql
	 * @param array $errors
	 */
	public function __construct($msg, $code, $fql, $errors = array()) {
		if (!is_array($fql)) {
			$fql = array($fql);
		}
		$this->fql = $fql;
		$this->errors = $errors;
		parent::__construct($msg, $code);
	}

	public function getFql() {
		return $this->fql;
	}

	/**
	 * Unused?
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}
}
