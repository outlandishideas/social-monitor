<?php

abstract class Badge_Abstract
{
	protected static $name = '';
	protected static $title = '';
	protected static $description;
	protected $db;

	protected $metrics = array();
	protected $metricsWeighting = array();

    public function __construct(PDO $db = null)
	{
		if (is_null($db)) {
			$db = Zend_Registry::get('db')->getConnection();
		}
		$this->db = $db;
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
            // if we are missing a single metric score, we cannot calculate the badge score
			if (is_null($score)) {
                return null;
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

	public static function getName()
	{
		return static::$name;
	}

	public static function getTitle()
	{
		return static::$title;
	}

	public static function getDescription()
	{
		return static::$description;
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
			':date'	=> $date->format('Y-m-d'),
			':range'	=> $range
		));
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		//foreach row (ordered by score of the current badge type) set the ranking
		$stmt = $this->db->prepare("UPDATE `badge_history` SET `{$name}_rank` = :rank WHERE `presence_id` = :id AND `date` = :date AND `daterange` = :range");
		$data = static::doRanking($data);
		foreach($data as $row) {
			$stmt->execute(array(
				':rank'		=> isset($row['rank']) && !empty($row['rank']) ? $row['rank'] : $defaultRank,
				':id'		=> $row['presence_id'],
				':date'		=> $date->format('Y-m-d'),
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

    static function getInstance() {
        return Badge_Factory::getBadge(self::getName());
    }

    public function setMetrics(array $metrics, $weighting = array())
    {
        $this->metrics = $metrics;
        $this->metricsWeighting = $weighting;
    }
}