<?php

/**
 * Failed to fetch up to date data but what data *was* fetched is returned
 */
class Exception_PartialResult extends RuntimeException
{
	private $data;

	/**
	 * @param string $message
	 * @param mixed $data
	 * @param Exception $previous
	 */
	public function __construct($message, $data, Exception $previous = null) {
		$this->data = $data;
		parent::__construct($message, 0, $previous);
	}

	/**
	 * @return mixed
	 */
	public function getData() {
		return $this->data;
	}
}
