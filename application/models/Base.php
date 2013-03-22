<?php

class FetchCount {
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
		return "Fetched $this->fetched $items";
	}
}

abstract class Model_Base
{
	protected static $columns = array();

	/**
	 * @var PDO
	 */
	protected $_db;

	protected $_row, $_tableName, $_sortColumn = null, $_isNew;
	
	public function __construct($data = null, $fromDb = false)
	{
		$this->_db = Zend_Registry::get('db')->getConnection();
		$this->_row = array();
		$this->_isNew = !$fromDb;

		if (is_array($data)) {
			$this->fromArray($data);
		}
	}

	public function getColumnNames()
	{
		$classname = get_called_class();
		if (!array_key_exists($classname, self::$columns)) {
			self::$columns[$classname] = array();
			$statement = $this->_db->prepare('SELECT column_name AS name FROM information_schema.columns WHERE table_schema=database() AND table_name=:tableName');
			$statement->execute(array(':tableName'=>$this->_tableName));
			foreach ($statement as $row) {
				self::$columns[$classname][] = $row['name'];
			}
		}
		return self::$columns[$classname];
	}
	
	public function __get($name)
	{
		$methodName = 'get'.ucfirst($name);
		if (method_exists($this, $methodName)) {
			return $this->$methodName();
		} elseif (in_array($name, $this->columnNames)) {
			return isset($this->_row[$name]) ? $this->_row[$name] : null;
		}
		return null;
	}
	
	public function __set($name, $value)
	{
		$methodName = 'set'.ucfirst($name);
		if (method_exists($this, $methodName)) {
			return $this->$methodName($value);
		} elseif (in_array($name, $this->columnNames)) {
			return $this->_row[$name] = $value;
		} else {
			return $this->$name = $value;
		}
	}
	
	public function find($id)
	{
		$statement = $this->_db->prepare('SELECT * FROM '.$this->_tableName.' WHERE id = ?');
		$statement->execute(array($id));
		if (!count($statement)) {
			throw new RuntimeException('ID not found in database');
		}
		$this->fromArray($statement->fetch(PDO::FETCH_ASSOC));
		$this->_isNew = false;
	}
	
	public function save()
	{
		$data = $this->_row;
		
		if ($this->_isNew) {
			$query = 'INSERT INTO '.$this->_tableName.' '.
				'(`'.implode('`,`', array_keys($data)).'`) '.
				'VALUES ('.implode(',', array_fill(0, count($data), '?')).')';
		} else {
			$query = 'UPDATE '.$this->_tableName.' '.
				'SET '.implode('=?, ', array_keys($data)).'=? '.
				'WHERE id=?';
			//add id to fill last placeholder
			$data[] = $this->id;
		}
		
		$statement = $this->_db->prepare($query);
		$statement->execute(array_values($data));

		if ($this->_isNew && empty($data['id'])) {
			$this->id = $this->_db->lastInsertId();
			$this->_isNew = false;
		}
				
	}
	
	public function delete()
	{
		$statement = $this->_db->prepare('DELETE FROM '.$this->_tableName.' WHERE id = ?');
		$statement->execute(array($this->id));
		$this->_row = array();
		$this->_isNew = true;
	}
	
	public function deleteIfEmpty()
	{
		foreach ($this->toArray() as $name=>$value) {
			if ($name != 'id' && $value) {
				return false;
			}
		}
		$this->delete();
		return true;
	}
	
	public function toArray($extraColumns = array())
	{
		$row = $this->_row;
		foreach ($extraColumns as $col) {
			$row[$col] = $this->$col;
		}
		return $row;
	}
	
	public function fromArray($data)
	{
		if ($data) {
			foreach ($data as $key => $value) {
				if (in_array($key, $this->columnNames)) {
					$this->{$key} = $value;
				}
			}
		}
	}

	public static function fetchById($id) {
		if (!is_scalar($id)) {
			return null;
		}
		return self::fetchBy('id', $id);
	}

	/**
	 * @param $col
	 * @param $key
	 * @return null|Model_Base
	 */
	public static function fetchBy($col, $key) {
		if ($key && is_scalar($key)) {
			$classname = get_called_class();
			$class = new $classname;
			$objects = $class->fetch(addslashes($col)." = ?", array($key));
			return ($objects ? $objects[0] : null);
		} else {
			return null;
		}
	}

	/**
	 * @param string|null $clause
	 * @param array $args
	 * @return Model_Base[]
	 */
	public static function fetchAll($clause = null, $args = array()) {
		$classname = get_called_class();
		$class = new $classname;
		return $class->fetch($clause, $args);
	}

	/**
	 * @param string|null $clause
	 * @param array $args
	 * @return Model_Base[]
	 */
	protected function fetch($clause = null, $args = array()) {
		$sql = 'SELECT * FROM '.$this->_tableName;
		if ($clause) {
			$sql .= ' WHERE ' . $clause;
		}
		$orderBy = $this->_sortColumn;
		if (is_numeric($orderBy)) {
			$orderBy = $this->columnNames[$orderBy];
		} else if (!in_array($orderBy, $this->columnNames)) {
			$orderBy = $this->columnNames[0];
		}
		$sql .= ' ORDER BY '.$orderBy;

		$statement = $this->_db->prepare($sql);
		$statement->execute($args);
		
		return $this->objectify($statement);
	}

	/**
	 * @param $data
	 * @return Model_Base[]
	 */
	public static function objectify($data) {
		$classname = get_called_class();
		$objects = array();
		foreach ($data as $row) {
			$objects[] = new $classname($row, true);
		}
		return $objects;
	}
	
	public static function insertData($tableName, $data) {
		$dataSize = count($data);
		if ($dataSize == 0) return 0;

		//get sample item (array is associative)
		$sampleItem = array_pop($data);
		array_push($data, $sampleItem);

		//create placeholders
		$columnNames = implode(',', array_keys($sampleItem));
		$placeholders = '(' . implode(',', array_fill(0, count($sampleItem), '?')) . ')';

		//create update clause
		$updaters = array();
		foreach ($sampleItem as $column => $value) {
			$updaters[] = "$column = VALUES($column)";
		}
		$updateClause = implode(',', $updaters);

		//chunk up inserts
		$maxInsertSize = 100;
		$lastQuery = null;
		for ($cursor = 0; $cursor < $dataSize; $cursor += $maxInsertSize) {
			$sliceData = array_slice($data, $cursor, $maxInsertSize);

			$allPlaceholders = implode(',', array_fill(0, count($sliceData), $placeholders));
			$query = "INSERT INTO $tableName ($columnNames) VALUES $allPlaceholders ON DUPLICATE KEY UPDATE $updateClause";

			//make single array of all values
			$values = array();
			foreach ($sliceData as $row) {
				foreach ($row as $col) $values[] = $col;
			}

			//insert the data
			if ($query != $lastQuery) {
				$statement = Zend_Registry::get('db')->prepare($query);
			}
			$statement->execute($values);
		}

		return 0;
	}

	// generates a sql ordering string from array $cols (name=>direction). any whose name
	// isn't in $validCols is ignored. If none are valid, empty string is returned
	protected static function generateOrderingString($cols, $validCols) {
		$c = array();
		foreach ($cols as $col=>$dir) {
			if (array_key_exists($col, $validCols)) {
				$c[] = $validCols[$col] . ' ' . $dir;
			}
		}
		
		return $c ? ' ORDER BY ' . implode(', ', $c) : '';
	}

	public static function localeDate($datetime) {
		$date = DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone('UTC'));
		$date->setTimezone(new DateTimeZone(date_default_timezone_get()));
		return $date->format('Y-m-d H:i:s');
	}
}
