<?php

abstract class Model_Base
{
	protected static $columns = array();
	protected static $tableName;
	protected static $sortColumn = 'id';



	/**
	 * @var PDO
	 */
	protected $_db;

	protected $_row, $_isNew;
	
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
		if (!array_key_exists($classname, Model_Base::$columns)) {
			Model_Base::$columns[$classname] = array();
			$statement = $this->_db->prepare('SELECT column_name AS name FROM information_schema.columns WHERE table_schema=database() AND table_name=:tableName');
			$statement->execute(array(':tableName'=>static::$tableName));
			foreach ($statement as $row) {
				Model_Base::$columns[$classname][] = $row['name'];
			}
		}
		return Model_Base::$columns[$classname];
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
			return $this->_row[$name] = $value;
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
			foreach ($data as $key => $value) {
				if (in_array($key, $columnNames)) {
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

        $stmt = $statement->fetchAll(PDO::FETCH_ASSOC);
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

		return $this->objectify($statement->fetchAll(PDO::FETCH_ASSOC));
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
		$inserted = 0;
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

    /*****************************************************************
     * Badge Factory
     *****************************************************************/

    public static function calculateTotalScores($data) {

	    $badgeTypes = Model_Badge::$ALL_BADGE_TYPES;

        //calculate total scores for each presence
        foreach($data as $typeData){
            foreach($typeData as $row){
                $total = 0;
                foreach($badgeTypes as $type){
                    if($type != Model_Badge::BADGE_TYPE_TOTAL) {
	                    $total += $row->$type;
                    }
                }
                $row->total = $total/(count($badgeTypes)-1);
            }

	        Model_Badge::assignRanks($typeData, 'total');
        }

        return $data;
    }

	/**
     * This function creates the four badges for the item it is called on.
     * @return array
     */
    public function badgeFactory(){

        //get the Badge Data from the presence_history table, or create ourselves if it doesn't exist
	    $endDate = new DateTime('now');
	    $startDate = new DateTime('now');
        $data = Model_Badge::getAllData('month', $startDate, $endDate);

        //take the raw data and organise it depending on how it will be used
        $badgeData = static::organizeBadgeData($data);

        //calculate the total scores for each row of data (after it has been organized)
        $badgeData = static::calculateTotalScores($badgeData);

        $badges = array();
        foreach($badgeData as $range => $rangeData){
            foreach(Model_Badge::$ALL_BADGE_TYPES as $type){
                $badges[$range][$type] = new Model_Badge($rangeData, $type, $this, get_called_class());
            }
        }

        return $badges;
    }

    /**
     * organize the raw data from db into badges. Each badge is an object with score and rank properties, to store arrays of key($presence_id) => value($score/$rank) pairs
     * @param $data
     * @return array
     */
    public static function organizeBadgeData($data){

        $badgeData = array();

        foreach($data as $row){
			if (!isset($badgeData[$row->daterange])) {
	            $badgeData[$row->daterange] = array();
			}
            $badgeData[$row->daterange][$row->presence_id] = $row;
        }

        return $badgeData;
    }

}
