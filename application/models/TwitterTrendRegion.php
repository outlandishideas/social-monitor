<?php

class Model_TwitterTrendRegion extends Model_TwitterBase {
	protected $_tableName = 'twitter_trend_regions';

	public function fetchTrends($token) {
		$result = $token->apiRequest('trends/place', array('id' => $this->woeid));
		$data = array_shift($result);
		
		$trends = array();

		// use the as_of date instead of the created_at date, as created_at may get stuck,
		// and we're only interested in the top 10 'now' (even if it's old data)
		$date = gmdate('Y-m-d H:i:s', strtotime($data->as_of));
		if ($date != $this->last_fetched) {
			foreach ($data->trends as $rank=>$trend) {
				$trends[] = array(
					'woeid' => $this->woeid,
					'name' => $trend->name,
					'normalised_name' => $trend->name,
					'rank' => $rank+1,// rank comes back 0-based (not 1-based)
					'as_of' => $date
				);
			}

			$this->last_fetched = $date;
			self::insertData('twitter_trend_topics', $trends);
		}
		return count($trends);
	}
	
	public function getRanking($dateRange, $topic, $bucketInterval) {
		$args = array(
			':woeid' => $this->woeid,
			':range_start' => gmdate('Y-m-d H:i:s', strtotime($dateRange[0])),
			':range_end' => gmdate('Y-m-d H:i:s', strtotime($dateRange[1])),
			':topic' => $topic
		);
		$statement = $this->_db->prepare(
			"SELECT DISTINCT topic.*, 
					from_unixtime($bucketInterval*floor(unix_timestamp(topic.as_of)/$bucketInterval)) AS date,
					topic.as_of = region.last_fetched AS is_latest
				FROM twitter_trend_topics AS topic
				INNER JOIN twitter_trend_regions AS region ON topic.woeid = region.woeid
				WHERE region.woeid = :woeid
				AND topic.name = :topic
				AND topic.as_of BETWEEN :range_start AND :range_end");
		$statement->execute($args);
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
	
	// groups all instances of the same topic text, producing an array of objects containing the text, frequency, average importance and average polarity
	public function getGroupedTopics($dateRange = null, $search = null, $ordering = array(), $limit = -1, $offset = -1)
	{
		$validOrderCols = array(
			'topic' => 'normalised_name', 
			'rank'  => 'average_rank',
			'age'   => 'age'
		);
		
		$args = array(
			':woeid' => $this->woeid,
			':range_start' => gmdate('Y-m-d H:i:s', strtotime($dateRange[0])),
			':range_end' => gmdate('Y-m-d H:i:s', strtotime($dateRange[1]))
		);

		$searchSql = ($search ? 'AND topic.normalised_name LIKE :search' : '');
		if ($search) {
			$args[':search'] = "%$search%";
		}
		
		$suffix = '';
		if ($limit >= 0) {
			$suffix .= " LIMIT $limit";
		}
		if ($offset >= 0) {
			$suffix .= " OFFSET $offset";
		}

		$sql = "SELECT topic.name, topic.normalised_name, AVG(topic.rank) AS average_rank, DATEDIFF(NOW(), MAX(topic.as_of)) AS age
			FROM twitter_trend_regions AS region 
			INNER JOIN twitter_trend_topics AS topic ON region.woeid = topic.woeid 
			WHERE region.woeid = :woeid 
			AND topic.as_of BETWEEN :range_start AND :range_end 
			$searchSql
			GROUP BY normalised_name";
		$order = self::generateOrderingString($ordering, $validOrderCols);
		if (!$order) {
			$order = self::generateOrderingString(array('average_rank'=>'DESC'), $validOrderCols);
		}
		$sql .= $order;

		$dataStatement = $this->_db->prepare("SELECT * FROM ($sql) AS data $suffix");
		$dataStatement->execute($args);
		$filteredCountStatement = $this->_db->prepare("SELECT COUNT(1) FROM ($sql) AS data");
		$filteredCountStatement->execute($args);
		
		$groupedTopics = $dataStatement->fetchAll(PDO::FETCH_OBJ);
		$filteredCount = array_shift($filteredCountStatement->fetchAll(PDO::FETCH_COLUMN));

		if ($searchSql) {
			$sql = str_replace($searchSql, '', $sql);
			unset($args[':search']);
			$unfilteredCountStatement = $this->_db->prepare("SELECT COUNT(1) FROM ($sql) AS data");
			$unfilteredCountStatement->execute($args);
			$unfilteredCount = array_shift($unfilteredCountStatement->fetchAll(PDO::FETCH_COLUMN));
		} else {
			$unfilteredCount = $filteredCount;
		}

		return (object)array('filteredCount'=>$filteredCount, 'unfilteredCount'=>$unfilteredCount, 'data'=>$groupedTopics);
	}
	
	public function getCurrentTopTopics()
	{
		$statement = $this->_db->prepare('SELECT DISTINCT topic.name 
			FROM twitter_trend_topics AS topic 
			INNER JOIN twitter_trend_regions AS region ON topic.woeid = region.woeid AND topic.as_of = region.last_fetched
			WHERE region.woeid = :woeid');
		$statement->execute(array(':woeid'=>$this->woeid));
		$data = $statement->fetchAll(PDO::FETCH_COLUMN);
		return array_values($data);
	}
	
	//TODO: do any of these need to be implemented by this class? does this class even need to inherit from twitter base?
	protected function getTweetListFromApiResult($apiResult) { return array(); }
	protected function createTweetInsertData($tweet) { return null; }
	protected function createUserInsertData($tweet) { return null; }
	protected function getFetchUrl() { return null; }
	protected function getFetchArgsArray() { return null; }
}