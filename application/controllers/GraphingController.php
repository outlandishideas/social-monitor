<?php


abstract class GraphingController extends BaseController {

	protected function chartOptions() {
		$container = $this->getContainer();
		return array(
			$container->get('chart.compare'),
			$container->get('chart.reach'),
			$container->get('chart.engagement'),
			$container->get('chart.quality'),
			$container->get('chart.popularity'),
			$container->get('chart.popularity-trend'),
			$container->get('chart.response-time')
		);
	}

	protected function tableMetrics(){
		$keys = array(
			Metric_Popularity::NAME,
			Metric_PopularityTime::NAME,
			Metric_ActionsPerDay::NAME,
			Metric_ResponseTime::NAME,
		);
		$metrics = array();
		foreach ($keys as $key) {
			$metrics[$key] = $this->translator->trans('metric.' . $key . '.title');
		}
		return $metrics;
	}

	protected function validateChartRequest()
	{
		$id = $this->_request->getParam('id');
		if(!$id) {
			$this->apiError($this->translator->trans('Error.missing-id')); //'Missing ID'
		}

		$dateRange = $this->getRequestDateRange();
		if (!$dateRange) {
			$this->apiError($this->translator->trans('Error.missing-date-range'));
		}

		$chartName = $this->_request->getParam('chart');
		if (!$chartName) {
			$this->apiError($this->translator->trans('Error.missing-chart-type'));//'Missing chart type');
		}

		$chart = $this->getContainer()->get('chart.' . $chartName);
		if(!$chart) {
			$this->apiError($this->translator->trans('Error.chart-doesnt-exist')); //'Chart type doesn\'t exist');
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

		$metrics[Metric_Popularity::NAME] = (object)array(
			'range' => array(0, 50, 100),
			'colors' => array($colors->red, $colors->yellow, $colors->green)
		);

		$audienceBest = self::getOption('achieve_audience_best');
		$audienceGood = self::getOption('achieve_audience_good');
		$audienceBad = self::getOption('achieve_audience_bad');
		$metrics[Metric_PopularityTime::NAME] = (object)array(
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
		$metrics[Metric_ActionsPerDay::NAME] = (object)array(
			'range' => array(0, $postsPerDay - $postsPerDayBad, $postsPerDay - $postsPerDayOk, $postsPerDay + $postsPerDayOk, $postsPerDay + $postsPerDayBad, max($postsPerDay + $postsPerDayBad + 1, 2*$postsPerDay)),
			'colors' => array($colors->red, $colors->yellow, $colors->green, $colors->green, $colors->yellow, $colors->red)
		);

        $relevanceTarget = (self::getOption('updates_per_day')/100)*self::getOption('facebook_relevance_percentage');
        $metrics[Metric_Relevance::NAME] = (object)array(
            'range' => array(0, $relevanceTarget/2, $relevanceTarget),
            'colors' => array($colors->red, $colors->yellow, $colors->green)
        );

		$responseTimeBest = self::getOption('response_time_best');
		$responseTimeGood = self::getOption('response_time_good');
		$responseTimeBad = self::getOption('response_time_bad');
		$metrics[Metric_ResponseTime::NAME] = (object)array(
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
            $badgeArgs->label = $badge->getTitle();
            $geochart[$badge->getName()] = $badgeArgs;
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
     * @param $model Model_Campaign|Model_Presence
     * @param Badge_Abstract $badge
     * @return array
     */
    public function badgeInformation($model, $badge)
	{
        $badgeData = $model->getBadges();
        if ($badgeData) {
    		$score = round($badgeData[$badge->getName()]);
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
		$badgeArr["title"] = $badge->getTitle();
        if ($badgeData) {
            $badgeArr["rank"] = array(
                "value" => $badgeData[$badge->getName()."_rank"], //get rank for badge
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
					"title" => $metric->getTitle(),
					"icon" => $metric->getIcon()
				);
				if ($model instanceof Model_Presence && $model->getType()->isMetricApplicable($metric)) {
					$metricScore = $metric->getScore($model, new \DateTime('-30 days'), new \DateTime());

					$metricColor = $colors->colors[0];
					foreach($colors->range as $i => $value){
						if($metricScore >= $value) {
			                $metricColor = $colors->colors[$i];
			            }
					}
					$m['score'] = $metricScore;
					$m['color'] = $metricColor;
					$m['gliding'] = $metric->isGliding();

					/**
					 *  We set the colour to grey if the score is null or 0.
					 *  If gliding, 0 and null imply no data, so we leave it grey.
					 *  If not gliding:
					 *   - null implies no data
					 *   - score between 0 and 1 implies a score of 0%, so we change to red
					 *
					 */

					if (!$m['gliding'] && $m['score'] !== null) {
						if ($m['score'] == 0 || $m['score'] < 1) {
							$m['color'] = '#D06959';	// red for score of 0%
						}
					}
					$metrics[] = $m;
				}
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