<?php

abstract class Badge_Abstract
{
	protected $name = '';
	protected $title = '';
	protected $description;
	protected $db;
	protected static $instance;

	protected $metrics = array();
	protected $metricsWeighting = array();

    protected function __construct(PDO $db = null)
	{
		if (is_null($db)) {
			$db = Zend_Registry::get('db')->getConnection();
		}
		$this->db = $db;

		// populate $name, $title, $description from transation files
		$translate = Zend_Registry::get('translate');
		$className = get_class($this);
		$this->name = $translate->_($className.'.name');
		$this->title = $translate->_($className.'.title');
		$this->description = $translate->_($className.'.description');
	}

	/**
	 * All implementations of this class should be singletons.
	 * This function, when inherited, provides access to the singleton.
	 *
	 * @param PDO|null $db
	 * @return Badge_Abstract
	 */
	public static function getInstance(PDO $db = null) {
		// use 'static' so that the child's instance property is used instead of this one
		// make sure the child class declares a static $instance property, otherwise this one will be inherited
		if(!static::$instance) {
			// returns the class that this static method has been called on, e.g. Badge_Engagement
			$class = get_called_class();
			static::$instance = new $class($db);
		}
		return static::$instance;
	}

	public function calculate(Model_Presence $presence, \DateTime $date = null, Enum_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
		}
		if (is_null($range)) {
			$range = Enum_Period::MONTH();
		}

		$totalWeight = 0;
		$totalScore = 0;
		$start = $range->getBegin($date);
		foreach ($this->getMetricsWeighting() as $metric => $weight) {
            $metric = $presence->getType()->getMetric($metric);
			if (!$metric) {
                continue; //only use metrics that are applicable to this presence
            }
			$score = $metric->getScore($presence, $start, $date);
			//previously we made the total score for the badge null if one of the badges were null
			//I have changed this as it was leading to misunderstandings when viewing presences
			//this was particularly true with sina weibo and the quality badge
			if (is_null($score)) {
                $score = 0;
            }
			$totalScore += ($score * $weight);
			$totalWeight += $weight;
		}
		if ($totalWeight == 0) {
            return null; //apparently we have no metrics to calculate a result with
        }
		$result = floor($totalScore/$totalWeight);
		$result = max(0, min(100, $result));

		return $result;
	}

	public function getName()
	{
		return $this->name;
	}

	public function getTitle()
	{
		return $this->title;
	}

	public function getDescription()
	{
		return $this->description;
	}

    /**
     * @return Metric_Abstract[]
     */
    public function getMetrics()
	{
		return $this->metrics;
	}

	public function getMetricsWeighting()
	{
		if (count($this->metricsWeighting) == 0) {
			$metrics = array();
			foreach($this->getMetrics() as $metric){
                $metricName = $metric::getName();
                //get weight from database, if it exists
                $weight = floatval(BaseController::getOption($metricName . '_weighting'));
                if (!$weight || $weight < 0) {
                    $weight = 1;
                }
				$metrics[$metricName] = $weight;
			}
			$this->metricsWeighting = $metrics;
		}
		return $this->metricsWeighting;
	}

	public function assignRanks(\DateTime $date = null, Enum_Period $range = null)
	{
		if (is_null($date)) {
			$date = new \DateTime();
			$date->modify('-1 day'); //badges always work on yesterday as the most recent day
		}
		if (is_null($range)) {
            $range = Enum_Period::MONTH();
        }

		$dateString = $date->format('Y-m-d');

        $defaultRank = count(Model_PresenceFactory::getPresences());

		$name = static::getName();

		$sql = "
			SELECT
				`h`.`presence_id`,
				`h`.`$name` AS `score`
			FROM
				badge_history AS h
			WHERE
				`date` = :date
				AND `daterange` = :range
		";

        $range = (string)$range;
		$stmt = $this->db->prepare($sql);
		$stmt->execute(array(
			':date'	=> $dateString,
			':range' => $range
		));
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		//foreach row (ordered by score of the current badge type) set the ranking
		$stmt = $this->db->prepare("UPDATE `badge_history` SET `{$name}_rank` = :rank WHERE `presence_id` = :id AND `date` = :date AND `daterange` = :range");
		$data = static::doRanking($data);
		foreach($data as $row) {
			$stmt->execute(array(
				':rank'		=> isset($row['rank']) && !empty($row['rank']) ? $row['rank'] : $defaultRank,
				':id'		=> $row['presence_id'],
				':date'		=> $dateString,
				':range'	=> $range
			));
		}
	}

    /**
     * Sort and rank the given data. Assumes a array key 'score' is present
     * @param array $data The data to sort and rank
     * @param string $scoreKey
     * @param string $rankKey
     * @return array The sorted and ranked data. An array key 'rank' has been added to each row.
     */
	public static function doRanking(&$data, $scoreKey = 'score', $rankKey = 'rank')
	{
		uasort($data, function($a, $b) use ($scoreKey) {
			$aVal = $a[$scoreKey];
			$bVal = $b[$scoreKey];

			if ($aVal == $bVal) return 0;

			return ($aVal > $bVal ? -1 : 1);
		});
		$lastScore = null;
		$lastRank = null;
        $i = 0;
		foreach($data as &$row) {
			if ($row[$scoreKey] == $lastScore){
				$rank = $lastRank;
			} else {
				$rank = $i+1;
			}

			$row[$rankKey] = $rank;

			$lastScore = $row[$scoreKey];
			$lastRank = $rank;
            $i++;
		}
		return $data;
	}

	public static function colorize($score)
	{
		$colors = array(
			'grey' => '#d2d2d2',
			'red' => '#D06959',
			'green' => '#84af5b',
			'orange' => '#F1DC63',
			'yellow' => '#FFFF50'
		);

        $map = array(
            0 => $colors['grey'],
            1 => $colors['red'],
            20 => $colors['red'],
            50 => $colors['yellow'],
            80 => $colors['green'],
            100 => $colors['green']
        );

		$color = $map[0];
		foreach($map as $min => $c){
			if($score >= $min) {
                $color = $c;
            }
		}
		return $color;
	}

    public function setMetrics(array $metrics, $weighting = array())
    {
        $this->metrics = $metrics;
        $this->metricsWeighting = $weighting;
    }
}