<?php

use Symfony\Bundle\FrameworkBundle\Translation\Translator;

use Outlandish\SocialMonitor\Database\Database;

use Outlandish\SocialMonitor\Helper\Verification;

use Outlandish\SocialMonitor\Exception\InvalidPropertyException;

use Outlandish\SocialMonitor\Exception\InvalidPropertiesException;
abstract class Model_Base
{
	protected static $tableColumns = array();

	protected static $tableName;
	protected static $sortColumn = 'id';

	/**
	 * @var Database
	 */
	protected static $db;

	/**
	 * @var Database
	 */
	protected $_db;

	protected $_row, $_isNew;

	public function __construct($data = null, $fromDb = false)
	{
		if(empty(self::$db)){
			$this->_db = Zend_Registry::get('db');
		} else {
			$this->_db = self::$db;
		}
		$this->_row = array();
		$this->_isNew = !$fromDb;

		if (is_array($data)) {
			$this->fromArray($data);
		}
	}

	public function verify($colName, $colValue){
		$tableDefinition = $this->getTableDefinition();
		$columnNames = Verification::pluck('name', $this->getTableDefinition());
		$searchIndex = array_search($colName, $columnNames);

		if($searchIndex < 0){
			throw new \InvalidArgumentException(ucfirst($colName) . ' does not exist in this table');
		}
		
		$columnDefiniton = $tableDefinition[$searchIndex];

		$isNullable = $columnDefiniton['nullable'];
		$hasDefault = Verification::truthyOrZero($columnDefiniton['default']);

		if(Verification::isNumericType($columnDefiniton['type']) && !Verification::isValidNumber($colValue)){
				throw new InvalidPropertyException($colName, 'is not a valid number.');
		}

		if(Verification::isStringType($columnDefiniton['type']) && strlen($colValue) > $columnDefiniton['maxLength']){
			throw new InvalidPropertyException($colName, 'is too long.');
		}
		
		if(Verification::truthyOrZero($colValue)){
			return $colValue;
		}else if(!$isNullable && $hasDefault){
			return $columnDefiniton['default'];
		}else if($isNullable && !$hasDefault){
			return null;
		}else{
			throw new InvalidPropertyException($colName, 'is required.');
		}
	}

	public function getColumnNames(){
		return Verification::pluck('name', $this->getTableDefinition());
	}

	public function getTableDefinition()
	{
		if (empty(self::$tableColumns)) {
			$statement = $this->_db->prepare('SELECT table_name, column_name, is_nullable, column_default, data_type, character_maximum_length 
											  FROM information_schema.columns WHERE table_schema=database()');
			$statement->execute();
			foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				if (!isset(Model_Base::$tableColumns[$row['table_name']])){
					Model_Base::$tableColumns[$row['table_name']] = array();
				}
				Model_Base::$tableColumns[$row['table_name']][] = [
					"name"=>$row['column_name'],
					"nullable"=>($row['is_nullable'] === 'YES'),
					"default"=>$row['column_default'],
					"type"=>$row['data_type'],
					"maxLength"=> $row['character_maximum_length']];
			}
		}
		if (isset(Model_Base::$tableColumns[static::$tableName])) {
			return Model_Base::$tableColumns[static::$tableName];
		}
		return array();
	}

	public function __get($name)
	{
		$methodName = 'get'.ucfirst($name);
		if (method_exists($this, $methodName)) {
			return $this->$methodName();
		} elseif (in_array($name, $this->getColumnNames())) {
			return isset($this->_row[$name]) ? $this->_row[$name] : null;
		}
		return null;
	}

	public function __set($name, $value)
	{
		$methodName = 'set'.ucfirst($name);
		if (method_exists($this, $methodName)) {
			return $this->$methodName($value);
		} elseif (in_array($name, $this->getColumnNames())) {
			$validatedValue = $this->verify($name, $value);
			return $this->_row[$name] = $validatedValue;
		} else {
			return $this->$name = $value;
		}
	}

	public function save()
	{
		$data = $this->_row;

		if ($this->_isNew) {
			$query = 'INSERT INTO '.static::$tableName.' '.
				'(`'.implode('`,`', array_keys($data)).'`) '.
				'VALUES ('.implode(',', array_fill(0, count($data), '?')).')';
		} else {
			$query = 'UPDATE '.static::$tableName.' '.
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
		$statement = $this->_db->prepare('DELETE FROM '.static::$tableName.' WHERE id = ?');
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
			$columnNames = $this->getColumnNames();
			$errorMessages = array();
			foreach ($data as $key => $value) {
				if (in_array($key, $columnNames)) {
					try {
						$this->{$key} = $value;
					}catch (InvalidPropertyException $ex){
						array_push($errorMessages, $ex);
					}
				}
			}
			if(!empty($errorMessages)){
				throw new InvalidPropertiesException('Invalid properties found.', $errorMessages);
			}
		}
	}

	public static function fetchById($id) {
		if (!is_scalar($id)) {
			return null;
		}
		return self::fetchBy('id', $id);
	}

	public static function fetchByIds($ids) {
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		return self::fetchAll("`id` IN (".implode(', ', array_fill(0, count($ids), '?')).")", $ids);
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
	public static function countAll($clause = null, $args = array()) {
		$classname = get_called_class();
		$class = new $classname;
		return $class->count($clause, $args);
	}

	/**
	 * @param string|null $clause
	 * @param array $args
	 * @return Model_Base[]
	 */
	protected function count($clause = null, $args = array()) {
		$sql = 'SELECT COUNT(1) as count FROM '.static::$tableName;
		if ($clause) {
			$sql .= ' WHERE ' . $clause;
		}

		$statement = $this->_db->prepare($sql);
		$statement->execute($args);

		$stmt = $statement->fetchAll(\PDO::FETCH_ASSOC);
		return intval($stmt[0]['count']);
	}

	/**
	 * @param string|null $clause
	 * @param array $args
	 * @return Model_Base[]
	 */
	protected function fetch($clause = null, $args = array()) {
		$sql = 'SELECT * FROM '.static::$tableName;
		if ($clause) {
			$sql .= ' WHERE ' . $clause;
		}
		$orderBy = static::$sortColumn;
		$columnNames = $this->getColumnNames();
		if (is_numeric($orderBy)) {
			$orderBy = $columnNames[$orderBy];
		} else if (!in_array($orderBy, $columnNames)) {
			$orderBy = $columnNames[0];
		}
		$sql .= ' ORDER BY ' . $orderBy;

		$statement = $this->_db->prepare($sql);
		$statement->execute($args);

		return $this->objectify($statement->fetchAll(\PDO::FETCH_ASSOC));
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
		$columns = array_keys($sampleItem);
		$columnNames = implode(',', $columns);
		$placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';

		//create update clause
		$updaters = array();
		foreach ($columns as $column) {
			$updaters[] = "$column = VALUES($column)";
		}
		$updateClause = implode(',', $updaters);

		//chunk up inserts
		$maxInsertSize = 100;
		$lastQuery = null;
		$inserted = 0;
		for ($cursor = 0; $cursor < $dataSize; $cursor += $maxInsertSize) {
			$sliceData = array_slice($data, $cursor, $maxInsertSize);

			$allPlaceholders = implode(',', array_fill(0, count($sliceData), $placeholders));
			$query = "INSERT INTO $tableName ($columnNames) VALUES $allPlaceholders ON DUPLICATE KEY UPDATE $updateClause";

			//make single array of all values
			$values = array();
			foreach ($sliceData as $row) {
				foreach ($columns as $column) {
					$values[] = $row[$column];
				}
			}

			//insert the data
			if ($query != $lastQuery) {
				$lastQuery = $query;
				$statement = Zend_Registry::get('db')->prepare($query);
			}
			/** @var $statement PDOStatement */
			$statement->execute($values);
			$inserted += $statement->rowCount();
		}

		return $inserted;
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

	public static function shortDate($datetime) {
		$date = DateTime::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone('UTC'));
		$date->setTimezone(new DateTimeZone(date_default_timezone_get()));
		return $date->format('d M | H:i');
	}

	public static function setDb(Database $db)
	{
		self::$db = $db;
	}

}
