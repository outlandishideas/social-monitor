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

    /*****************************************************************
     * Badge Factory
     *****************************************************************/

    /**
     * function gets returns rows for all Badge data stored in the presence_history for today's date
     * If badge data is not yet in the table for today, it will calculate it and insert it and then return it
     * @param int
     * @return array
     */
    public static function getBadgeData($id = null) {

        $class = get_called_class();
        $countItems = $class::countAll();

        //get today's date
        $date = new DateTime();
        $startDate = $date->format('Y-m-d');

        $clauses = array();

        //start and end dateTimes return all entries from today's date
        $clauses[] = 'datetime >= :start_date';
        $clauses[] = 'datetime <= :end_date';
        $args[':start_date'] = $startDate . ' 00:00:00';
        $args[':end_date'] = $startDate . ' 23:59:59';

        if($id){
            $clauses[] = 'presence_id = :id';
            $args[':id'] = $id;
        }

        //returns rows with presence_id, type, value and datetime, ordered by presence_id, type, and datetime(DESC)
        $sql =
            'SELECT *
            FROM badge_history
            WHERE '.implode(" AND ", $clauses).'
            ORDER BY presence_id, datetime DESC';

        $stmt = Zend_Registry::get('db')->prepare($sql);
        $stmt->execute($args);
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);

        //if too few rows are returned
        if(count($data)/count(Model_Presence::$BADGE_RANGES) != $countItems){

            $data = self::calculatePresenceBadgeData($data, $date);

        }

        return $data;
    }

    public static function calculatePresenceBadgeData($data, $date){
        //get back array of presences that are missing data
        $presences = Model_Presence::fetchAll(null, array());

        $dateString = $date->format('Y-m-d H-i-s');

        //create a variable that will hold the data that will be
        $setHistoryArgs = array();

        //foreach presence and foreach badge (not total badge), calculate the metric
        foreach(Model_Presence::$BADGE_RANGES as $type){

            $tempData = array();

            foreach($presences as $presence){

                $dataRow = (object)array(
                    'presence_id' => $presence->id,
                    'datetime' => $dateString,
                    'type' => $type
                );

                foreach(Model_Presence::ALL_BADGES() as $badgeType => $metrics){

                    //$dataRow is an object with four properties: presence_id, type, value, datetime (matching columns in presence_history table)
                    $dataRow->$badgeType = $presence->getMetricsScore($badgeType, $metrics, $type);


                }

                $tempData[] = $dataRow;

            }

            foreach(Model_Presence::ALL_BADGES() as $badge => $metric){

                usort($tempData, function($a, $b) use ($badge){
                    if($a->$badge == $b->$badge) return 0;
                    return $a->$badge > $b->$badge ? -1 : 1;
                });

                $lastScore = null;
                $ranking = 1;
                foreach($tempData as $row) {
                    //if score is not equal to last score increase ranking by 1
                    if(is_numeric($lastScore) && $lastScore != $row->$badge){
                        $ranking++;
                    }

                    $rankType = $badge.'_rank';

                    $row->$rankType = $ranking;

                    //set current score to $lastScore for next value in array
                    $lastScore = $row->$badge;
                }
            }

            foreach($tempData as $row){
                //we have to turn the object back into an array to send it to insertData
                $setHistoryArgs[] = (array)$row;
            }

            $data = array_merge($data, $tempData);

        }

        //insert the newly calculated data back into the presence_history table, so next time its ready for us.
        Model_Base::insertData('badge_history', $setHistoryArgs);

        return $data;
    }

    public static function calculateTotalScores($data) {
        //calculate total scores for each presence
        foreach($data as $type => $typeData){
            foreach($typeData as $row){
                $row->total = $row->reach + $row->engagement + $row->quality;
                $row->total /= 3;
            }

            usort($typeData, function($a, $b){
                if($a->total == $b->total) return 0;
                return $a->total > $b->total ? -1 : 1 ;
            });

            $lastScore = null;
            $ranking = 1;
            foreach($typeData as $row){
                //if score is not equal to last score increase ranking by 1
                if(is_numeric($lastScore) && $lastScore != $row->total){
                    $ranking++;
                }

                $row->total_rank = $ranking;

                //set current score to $lastScore for next value in array
                $lastScore = $row->total;
            }
        }

        return $data;
    }

    /**
     * This function creates the four badges for the item it is called on.
     * @return array
     */
    public function badgeFactory(){

        //set up some variables that we we will use throughout this function
        $date = new DateTime();
        $class = get_called_class();
        $setHistoryArgs = array();
        $countItems = $class::countAll();

        //get the Badge Data from the presence_history table, or create ourselves if it doesn't exist
        $data = $class::getBadgeData();

        //take the raw data and organise it depending on how it will be used
        $badgeData = $class::organizeBadgeData($data);

        $badgeData = $class::calculateTotalScores($badgeData);

        $badges = array();
        foreach($badgeData as $type => $typeData){

            foreach(Model_Presence::ALL_BADGES() as $badge => $metrics){

                //create a Badge Object from this data
                $badges[$type][$badge] = new Model_Badge($typeData, $badge, $this, $class);

            }

            $total = array('total' => new Model_Badge($typeData, 'total', $this, $class));
            //add the the total badge at the beginning of the $badges array
            $badges[$type] = $total + $badges[$type];
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

        foreach($data as $k => $row){

            $badgeData[$row->type][$row->presence_id] = $row;
        }

        return $badgeData;
    }

}
