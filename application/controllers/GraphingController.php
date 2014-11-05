<?php


abstract class GraphingController extends BaseController {

	protected function chartOptions() {
		return array(
			Chart_Compare::getInstance(),
			Chart_Reach::getInstance(),
			Chart_Engagement::getInstance(),
			Chart_Quality::getInstance(),
			Chart_Popularity::getInstance(),
			Chart_PopularityTrend::getInstance()
		);
	}

	protected static function tableMetrics(){
		return array(
            Metric_Popularity::getName() => 'Percent of Target Audience',
            Metric_PopularityTime::getName() => 'Time to Target Audience',
            Metric_ActionsPerDay::getName() => 'Actions Per Day',
            Metric_ResponseTime::getName() => 'Response Time',
		);
	}

	protected function validateChartRequest()
	{
		$id = $this->_request->getParam('id');
		if(!$id) {
			$this->apiError('Missing ID');
		}

		$dateRange = $this->getRequestDateRange();
		if (!$dateRange) {
			$this->apiError('Missing date range');
		}

		$chart = $this->_request->getParam('chart');
		if (!$chart) {
			$this->apiError('Missing chart type');
		}

		if(!in_array($chart, Chart_Factory::getChartNames())) {
			$this->apiError('Chart type doesn\'t exist');
		}
	}

	public function init() {
		parent::init();

		$colors = (object)array(
            'grey' => '#d2d2d2',
			'red' => '#D06959',
			'green' => '#84af5b',
			'orange' => '#F1DC63',
			'yellow' => '#FFFF50'
		);
		$metrics = array();

        $metrics['digital_population'] = (object)array(
            'range' => array(0, 25, 50, 75, 100),
            'colors' => array($colors->red, $colors->orange, $colors->yellow, $colors->green, $colors->green)
        );

		$metrics[Metric_Popularity::getName()] = (object)array(
			'range' => array(0, 50, 100),
			'colors' => array($colors->red, $colors->yellow, $colors->green)
		);

		$audienceBest = self::getOption('achieve_audience_best');
		$audienceGood = self::getOption('achieve_audience_good');
		$audienceBad = self::getOption('achieve_audience_bad');
		$metrics[Metric_PopularityTime::getName()] = (object)array(
			'range' => array($audienceBest, $audienceGood, $audienceBad, $audienceGood+$audienceBad),
			'colors' => array($colors->green, $colors->yellow, $colors->orange, $colors->red)
		);
		$metrics['popularity_rate'] = (object)array(
			'range' => array(0, 33, 66, 100),
			'colors' => array($colors->red, $colors->orange, $colors->yellow, $colors->green)
		);

		$postsPerDay = self::getOption('updates_per_day');
		$postsPerDayOk = self::getOption('updates_per_day_ok_range');
		$postsPerDayBad = self::getOption('updates_per_day_bad_range');
		$metrics[Metric_ActionsPerDay::getName()] = (object)array(
			'range' => array(0, $postsPerDay - $postsPerDayBad, $postsPerDay - $postsPerDayOk, $postsPerDay + $postsPerDayOk, $postsPerDay + $postsPerDayBad, max($postsPerDay + $postsPerDayBad + 1, 2*$postsPerDay)),
			'colors' => array($colors->red, $colors->yellow, $colors->green, $colors->green, $colors->yellow, $colors->red)
		);

        $relevanceTarget = (self::getOption('updates_per_day')/100)*self::getOption('facebook_relevance_percentage');
        $metrics[Metric_Relevance::getName()] = (object)array(
            'range' => array(0, $relevanceTarget/2, $relevanceTarget),
            'colors' => array($colors->red, $colors->yellow, $colors->green)
        );

		$responseTimeBest = self::getOption('response_time_best');
		$responseTimeGood = self::getOption('response_time_good');
		$responseTimeBad = self::getOption('response_time_bad');
		$metrics[Metric_ResponseTime::getName()] = (object)array(
			'range' => array(0, $responseTimeBest, $responseTimeGood, $responseTimeBad),
			'colors' => array($colors->green, $colors->yellow, $colors->orange, $colors->red)
		);
		$this->assignRgbColors($metrics);

        $geochart = array();
        $badges = Badge_Factory::getBadges();
        foreach($badges as $badge){
            $badgeArgs = new stdClass();
            $badgeArgs->range = array(0, 1, 20, 50, 80, 100);
            $badgeArgs->colors = array($colors->grey, $colors->red, $colors->red, $colors->yellow, $colors->green, $colors->green);
            $badgeArgs->label = $badge::getTitle();
            $geochart[$badge::getName()] = $badgeArgs;
        }
		$this->assignRgbColors($geochart);

        $this->view->geochartMetrics = $geochart;
        $this->view->trafficMetrics = $metrics+$geochart;

	}

	/**
	 * converts each hex string to rgb values
	 * @param $items
	 */
	function assignRgbColors($items) {
		foreach ($items as $args) {
			$args->colorsRgb = array();
			foreach ($args->colors as $color) {
				$rgb = array();
				for ($i=1; $i<6; $i+=2) {
					$rgb[] = hexdec(substr($color, $i, 2));
				}
				$args->colorsRgb[] = $rgb;
			}
		}
	}

    /**
     * @return Header_Abstract[]
     */
    protected function tableIndexHeaders() {
        return array();
    }

    /**
     * @param $model Model_Campaign|Model_Presence
     * @param Badge_Abstract $badge
     * @return array
     */
    public function badgeInformation($model, $badge)
	{
        $badgeData = $model->getBadges();
        if ($badgeData) {
    		$score = round($badgeData[$badge::getName()]);
        } else {
            $score = 0;
        }

		$colors = $this->view->geochartMetrics[$badge->getName()];
		$color = $colors->colors[0];
		foreach($colors->range as $i => $value){
			if($score >= $value) {
                $color = $colors->colors[$i];
            }
		}

		$badgeArr = array();
		$badgeArr["title"] = $badge::getTitle();
        if ($badgeData) {
            $badgeArr["rank"] = array(
                "value" => $badgeData[$badge::getName()."_rank"], //get rank for badge
                "denominator" => $badgeData['denominator'] //get count of $model type
            );
        } else {
            $badgeArr["rank"] = array(
                "value" => '?',
                "denominator" => '?'
            );
        }
		$badgeArr['score'] = array(
			"value"	=> $score, //get score badge
			"color" => $color //get colour for this score
		);
        $badgeMetrics = $badge->getMetrics();
		if(count($badgeMetrics) > 0){
			$metrics = array();
			foreach($badgeMetrics as $metric){
				$m = array(
					"title" => $metric::getTitle(),
					"icon" => $metric::getIcon()
				);
				if ($model instanceof Model_Presence && $model->getType()->isMetricApplicable($metric)) {
					$metricScore = $metric->getScore($model, new \DateTime('-30 days'), new \DateTime());
					if (is_null($metricScore)) {
                        $metricScore = 0;
                    }
					$metricColor = $colors->colors[0];
					foreach($colors->range as $i => $value){
						if($metricScore >= $value) {
			                $metricColor = $colors->colors[$i];
			            }
					}
					$m['score'] = $metricScore;
					$m['color'] = $metricColor;
				}
				$metrics[] = $m;
			}
			$badgeArr['metrics'] = $metrics;
		}
		return $badgeArr;
	}

    /**
     * @param $model Model_Campaign|Model_Presence
     * @return array
     */
    public function badgeDetails($model)
	{
		$badges = array(
            'main' => null,
            'small' => array()
        );

		foreach(Badge_Factory::getBadges() as $badge){
            $currentBadgeData = $this->badgeInformation($model, $badge);
            if ($badge instanceof Badge_Total) {
                $badges['main'] = $currentBadgeData;
            } else {
                $badges['small'][] = $currentBadgeData;
            }
		}

		return $badges;
	}

}