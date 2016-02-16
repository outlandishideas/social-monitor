<?php

class Exception_InstagramApi extends RuntimeException {

	protected $errors;

	/**
	 * @param string $msg
	 * @param int $code
	 * @param array $errors
	 */
	public function __construct($msg, $code, $errors = array()) {
		$this->errors = $errors;
		parent::__construct($msg, $code);
	}

	/**
	 * Unused?
	 * @return array
	 */
	public function getErrors() {
		return $this->errors;
	}

//	public function getToken() {
//		return $this->token;
//	}
}
