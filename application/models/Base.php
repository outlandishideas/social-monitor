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
     * @param object $startDate
     * @param object $endDate
     * @return array
     */
    public static function getBadgeData($startDate = null, $endDate = null) {

        //get called class and count all the objects for this class
        //(not accurate for campaigns as we don't return campaigns with no presences
        $class = get_called_class();
        $countItems = $class::countAll();

        //if we haven't set a start and end date set it up
        if(!$startDate || !$endDate){
            $endDate = new DateTime();
            $startDate = clone $endDate;
        }

        $clauses = array();

        //start and end dateTimes return all entries from start to end date inclusive
        $clauses[] = 'datetime >= :start_date';
        $clauses[] = 'datetime <= :end_date';
        $args[':start_date'] = $startDate->format('Y-m-d') . ' 00:00:00';
        $args[':end_date'] = $endDate->format('Y-m-d') . ' 23:59:59';

        //returns rows with all columns from badge_history table, ordered by presence_id and datetime(DESC)
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

            //if too few rows, go off and calculate them for missing presences
            $data = self::calculatePresenceBadgeData($data, $endDate);

        }

        return $data;
    }

    public static function calculatePresenceBadgeData($data, $date = null){

        //get back array of presences that are missing data
        $presences = Model_Presence::fetchAll();

        //get todays date for datetime column for data
        if (!$date) $date = new DateTime();
        $dateString = $date->format('Y-m-d H:i:s');

        //create a variable that will hold the data that will be sent to insertData
        $setHistoryArgs = array();

        //foreach presence and foreach badge (not total badge), calculate the metrics
        foreach(Model_Presence::$BADGE_RANGES as $type){

            //use tempData so we can keep the ranges separate when calculating ranking
            $tempData = array();

            foreach($presences as $presence){

                //$dataRow is an object with four properties: presence_id, type, value, datetime (matching columns in presence_history table)
                $dataRow = (object)array(
                    'presence_id' => $presence->id,
                    'datetime' => $dateString,
                    'type' => $type
                );


                foreach(Model_Presence::ALL_BADGES() as $badgeType => $metrics){

                    //return score from the metrics from this badge and add it to the dataRow
                    $dataRow->$badgeType = $presence->getMetricsScore($badgeType, $metrics, $type);

                }

                //add the dataRow to tempData so we can rank it before we add it to the main data array
                $tempData[] = $dataRow;

            }

            //foreach badge (not total), sort the tempData and then rank it
            foreach(Model_Presence::ALL_BADGES() as $badge => $metric){

                //sorts the $tempData by the current badge score
                usort($tempData, function($a, $b) use ($badge){
                    if($a->$badge == $b->$badge) return 0;
                    return $a->$badge > $b->$badge ? -1 : 1;
                });

                //set variables for ranking
                $lastScore = null;
                $ranking = 1;

                //foreach dataRow, ordered by score of the current badge, set the ranking
                foreach($tempData as $row) {

                    //if score is not equal to last score increase ranking by 1
                    if(is_numeric($lastScore) && $lastScore != $row->$badge){
                        $ranking++;
                    }

                    //add current $ranking to dataRow
                    $rankType = $badge.'_rank';
                    $row->$rankType = $ranking;

                    //set current score to $lastScore for next value in array
                    $lastScore = $row->$badge;
                }
            }

            //add the tempData for this specifc range onto the data array
            $data = array_merge($data, $tempData);

        }

        //we have to turn the object back into an array to send it to insertData
        foreach($tempData as $row){

            $setHistoryArgs[] = (array)$row;
        }

        //insert the newly calculated data back into the presence_history table, so next time its ready for us.
        Model_Base::insertData('badge_history', $setHistoryArgs);

        return $data;
    }

    public static function calculateTotalScores($data) {

        //calculate total scores for each presence
        foreach($data as $type => $typeData){

            foreach($typeData as $row){

                $total = 0;
                $badges = Model_Presence::ALL_BADGES();

                foreach($badges as $badge => $metrics){
                    $total += $row->$badge;
                }

                $row->total = $total/count($badges);

            }

            //sorts the $tempData by the total score
            usort($typeData, function($a, $b){
                if($a->total == $b->total) return 0;
                return $a->total > $b->total ? -1 : 1 ;
            });

            //set variables for ranking
            $lastScore = null;
            $ranking = 1;

            //foreach dataRow, ordered by score of the current badge, set the ranking
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

        //get called class
        $class = get_called_class();

        //get the Badge Data from the presence_history table, or create ourselves if it doesn't exist
        $data = static::getBadgeData();

        //take the raw data and organise it depending on how it will be used
        $badgeData = $class::organizeBadgeData($data);

        //calculate the total scores for each row of data (after it has been organized)
        $badgeData = $class::calculateTotalScores($badgeData);

        $badges = array();
        foreach($badgeData as $range => $rangeData){

            foreach(Model_Presence::ALL_BADGES() as $badge => $metrics){

                //create a Badge Object from this data
                $badges[$range][$badge] = new Model_Badge($rangeData, $badge, $this, $class);

            }

            $total = array('total' => new Model_Badge($rangeData, 'total', $this, $class));
            //add the the total badge at the beginning of the $badges array
            $badges[$range] = $total + $badges[$range];
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
