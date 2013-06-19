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
	
	public function find($id)
	{
		$statement = $this->_db->prepare('SELECT * FROM '.static::$tableName.' WHERE id = ?');
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
        return $stmt[0]['count'];
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

    public static function getBadgeData() {

        $date = new DateTime('-1 day');
        $startDate = $date->format('Y-m-d');

        $types = array('reach','reach_ranking','engagement','engagement_ranking','quality','quality_ranking');

        $args[':start_date'] = $startDate . ' 00:00:00';
        $args[':end_date'] = $startDate . ' 23:59:59';

        $sql =
            'SELECT p.id as presence_id, ph.type as type, ph.value as value, ph.datetime as datetime
            FROM presences as p
            LEFT JOIN presence_history as ph
            ON ph.presence_id = p.id
            WHERE ph.datetime >= :start_date
            AND ph.datetime <= :end_date
            AND ph.type IN ("'. implode('","',$types) .'")
            ORDER BY p.id, p.type, ph.datetime DESC';

        $stmt = Zend_Registry::get('db')->prepare($sql);
        $stmt->execute($args);
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        if(empty($data)){

            $presences = Model_Presence::fetchAll();
            $setHistoryArgs = array();

            foreach($presences as $presence){
                foreach(Model_Presence::ALL_BADGES() as $badgeType => $metrics){
                    $dataRow = $presence->calculateMetrics($badgeType, $metrics, $date->format('Y-m-d H-i-s'));
                    $data[] = $dataRow;
                    $setHistoryArgs[] = (array)$dataRow;
                }
            }

            Model_Base::insertData('presence_history', $setHistoryArgs);

        }

        return $data;
    }

    public function badgeFactory(){

        $date = new DateTime();
        $class = get_called_class();
        $setHistoryArgs = array();
        $countItems = $class::countAll();

        $data = $this->getBadgeData();

        $badgeData = $class::organizeBadgeData($data);

        $badges = array();
        foreach($badgeData as $type => $badge){

            //check count of score against all presences
            //if we are missing some presences get all presences and array_diff_key  id
            if(count($badge->score) != $countItems && $class == 'Model_Presence'){

                if(!isset($allPresenceIds)) {
                    $allPresences = Model_Presence::fetchAll(null, array(), array('id'));
                    $allPresenceIds = array();
                    foreach($allPresences as $p){
                        $allPresenceIds[$p->presence_id] = $p;
                    }
                }

                $missingIds = array_diff_key($allPresenceIds, $badge->score);
                foreach($missingIds as $presence){
                    $dataRow = $presence->calculateMetrics($type, $metrics = array(), $date->format('Y-m-d H-i-s'));
                    $badge->score[$dataRow->presence_id] = $dataRow->value;
                    $setHistoryArgs[] = (array)$dataRow;
                }


            }

            $badges[$type] = new Model_Badge($badge, $type, $this, $class);
        }

        $total = array('total' => new Model_Badge($badges, 'total', $this, $class));
        $badges = $total + $badges;

        $this->insertData('presence_history', $setHistoryArgs);

        return $badges;

    }

    public function organizeBadgeData($data){

        $badgeData = array();

        foreach($data as $row){

            if(preg_match('|^(.*)\_ranking$|', $row->type, $matches)){
                $badgeType = $matches[1];
                $rank = true;
            } else {
                $badgeType = $row->type;
                $rank = false;
            }

            if(!isset($badgeData[$badgeType])) $badgeData[$badgeType] = (object)array('score'=>array(), 'rank'=>array());

            if($rank){
                if(!array_key_exists($row->presence_id, $badgeData[$badgeType]->rank)) $badgeData[$badgeType]->rank[$row->presence_id] = $row->value;
            } else {
                if(!array_key_exists($row->presence_id, $badgeData[$badgeType]->score)) $badgeData[$badgeType]->score[$row->presence_id] = $row->value;
            }

        }

        return $badgeData;
    }

}
