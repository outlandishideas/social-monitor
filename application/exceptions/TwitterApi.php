<?php

class Exception_TwitterApi extends RuntimeException {

	protected $path;//, $token;
	protected $errors;

	/**
	 * @param string $msg
	 * @param int $code
	 * @param string $path
	 * @param array $errors
	 */
	public function __construct($msg, $code, $path, $errors = array()) {
		$this->path = $path;
		$this->errors = $errors;
//		$this->token = $token;
		parent::__construct($msg, $code);
	}

	public function getPath() {
		return $this->path;
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
