<?php

namespace Outlandish\SocialMonitor\Database;

/**
 * Wrapper for PDO class
 */
class Database
{
	/** @var \PDO */
	protected $pdo;
	protected $dsn, $username, $password;

	public function __construct($dsn, $username, $password)
	{
		$this->dsn = $dsn;
		$this->username = $username;
		$this->password = $password;
	}


	public function closeConnection() {
		$this->pdo = null;
	}

	public function getConnection() {
		if (!$this->pdo) {
			$this->pdo = new \PDO($this->dsn, $this->username, $this->password);
		}
		return $this->pdo;
	}

	public function prepare ($statement, array $driver_options = array()) {
		return $this->getConnection()->prepare($statement, $driver_options);
	}

	public function beginTransaction () {
		return $this->getConnection()->beginTransaction();
	}

	public function commit () {
		return $this->getConnection()->commit();
	}

	public function rollBack () {
		return $this->getConnection()->rollBack();
	}

	public function inTransaction () {
		return $this->getConnection()->inTransaction();
	}

	public function setAttribute ($attribute, $value) {
		return $this->getConnection()->setAttribute($attribute, $value);
	}

	public function exec ($statement) {
		return $this->getConnection()->exec($statement);
	}

	public function query ($statement, $mode = \PDO::ATTR_DEFAULT_FETCH_MODE) {
		return $this->getConnection()->query($statement, $mode);
	}

	public function lastInsertId ($name = null) {
		return $this->getConnection()->lastInsertId($name);
	}

	public function lastRowCount() {
		return (int)$this->getConnection()->query('SELECT FOUND_ROWS()')->fetchColumn();
	}

	public function errorCode () {
		return $this->getConnection()->errorCode();
	}

	public function errorInfo () {
		return $this->getConnection()->errorInfo();
	}

	public function getAttribute ($attribute) {
		return $this->getConnection()->getAttribute($attribute);
	}

	public function quote ($string, $parameter_type = \PDO::PARAM_STR) {
		return $this->getConnection()->quote($string, $parameter_type);
	}

	final public function __wakeup () {
		$this->getConnection()->__wakeup();
	}

	final public function __sleep () {
		$this->getConnection()->__sleep();
	}

	public static function getAvailableDrivers () {
		return \PDO::getAvailableDrivers();
	}

}