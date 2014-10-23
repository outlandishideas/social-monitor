<?php


abstract class GraphingController extends BaseController {

	protected function graphMetrics() {
		return array(
			Chart_Compare::getName() => Chart_Compare::getTitle(),
			Chart_Reach::getName() => Chart_Reach::getTitle(),
			Chart_Engagement::getName() => Chart_Engagement::getTitle(),
			Chart_Quality::getName() => Chart_Quality::getTitle()
		);
	}

	protected static function tableMetrics(){
		return array(
			Model_Presence::METRIC_POPULARITY_PERCENT => 'Percent of Target Audience',
			Model_Presence::METRIC_POPULARITY_TIME => 'Time to Target Audience',
			Model_Presence::METRIC_POSTS_PER_DAY => 'Actions Per Day',
			Model_Presence::METRIC_RESPONSE_TIME => 'Response Time',
		);
	}

	protected function validateChartRequest()
	{
		$id = $this->_request->id;
		if(!$id) {
			$this->apiError('Missing Id range');
		}

		$dateRange = $this->getRequestDateRange();
		if (!$dateRange) {
			$this->apiError('Missing date range');
		}

		$chart = $this->_request->chart;
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

		$metrics[Model_Presence::METRIC_POPULARITY_PERCENT] = (object)array(
			'range' => array(0, 50, 100),
			'colors' => array($colors->red, $colors->yellow, $colors->green)
		);

		$audienceBest = self::getOption('achieve_audience_best');
		$audienceGood = self::getOption('achieve_audience_good');
		$audienceBad = self::getOption('achieve_audience_bad');
		$metrics[Model_Presence::METRIC_POPULARITY_TIME] = (object)array(
			'range' => array($audienceBest, $audienceGood, $audienceBad, $audienceGood+$audienceBad),
			'colors' => array($colors->green, $colors->yellow, $colors->orange, $colors->red)
		);
		$metrics[Model_Presence::METRIC_POPULARITY_RATE] = (object)array(
			'range' => array(0, 33, 66, 100),
			'colors' => array($colors->red, $colors->orange, $colors->yellow, $colors->green)
		);

		$postsPerDay = self::getOption('updates_per_day');
		$postsPerDayOk = self::getOption('updates_per_day_ok_range');
		$postsPerDayBad = self::getOption('updates_per_day_bad_range');
		$metrics[Model_Presence::METRIC_POSTS_PER_DAY] = (object)array(
			'range' => array(0, $postsPerDay - $postsPerDayBad, $postsPerDay - $postsPerDayOk, $postsPerDay + $postsPerDayOk, $postsPerDay + $postsPerDayBad, max($postsPerDay + $postsPerDayBad + 1, 2*$postsPerDay)),
			'colors' => array($colors->red, $colors->yellow, $colors->green, $colors->green, $colors->yellow, $colors->red)
		);

        $relevanceTarget = (self::getOption('updates_per_day')/100)*self::getOption('facebook_relevance_percentage');
        $metrics[Model_Presence::METRIC_RELEVANCE] = (object)array(
            'range' => array(0, $relevanceTarget/2, $relevanceTarget),
            'colors' => array($colors->red, $colors->yellow, $colors->green)
        );

		$responseTimeBest = self::getOption('response_time_best');
		$responseTimeGood = self::getOption('response_time_good');
		$responseTimeBad = self::getOption('response_time_bad');
		$metrics[Model_Presence::METRIC_RESPONSE_TIME] = (object)array(
			'range' => array(0, $responseTimeBest, $responseTimeGood, $responseTimeBad),
			'colors' => array($colors->green, $colors->yellow, $colors->orange, $colors->red)
		);
		$this->assignRgbColors($metrics);

        $geochart = array();
        $badges = Model_Badge::$ALL_BADGE_TYPES;
        foreach($badges as $type){
            $geochart[$type] = (object)array(
                'range' => array(0, 1, 20, 50, 80, 100),
                'colors' => array($colors->grey, $colors->red, $colors->red, $colors->yellow, $colors->green, $colors->green)
            );
        }
		foreach (Model_Badge::$ALL_BADGE_TYPES as $type) {
            $geochart[$type]->label = Model_Badge::badgeTitle($type);
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

    public static function tableHeader($type, $csv = true){
        $header = new stdClass();
	    $header->name = $type;
	    $header->csv = $csv;
	    $header->sort = 'auto';
	    $header->title = '';
	    $header->desc = null;

        $metrics = self::tableMetrics();

	    if (array_key_exists($type, $metrics)) {
		    $header->sort = 'traffic-light';
		    $header->width = '150px';
		    $header->title = $metrics[$type];
	    }

        switch($type){
            case 'name' :
				$header->title = 'Name';
                break;
            case 'country':
				$header->title = 'Country';
                break;
            case 'countries':
				$header->title = 'Countries';
                break;
            case 'total-rank':
				$header->sort = 'numeric';
				$header->title = 'Overall Rank';
                $header->desc = 'Overall Rank shows the rank of this presence or group when compared against others.';
                break;
            case 'total-score':
				$header->sort = 'numeric';
				$header->title = 'Overall Score';
				$header->desc = 'Overall Score shows the combined scores of the three badges, Reach, Engagement and Quality.';
                break;
            case 'current-audience':
				$header->sort = 'fuzzy-numeric';
				$header->title = 'Audience';
				$header->desc = 'Audience is the currently measured audience for this presence.';
                break;
            case 'target-audience':
				$header->sort = 'fuzzy-numeric';
	            $header->title = 'Target Audience';
                $header->desc = 'Target Audience is the audience that must be reached by this presence or group of presences.';
                break;
            case 'digital-population':
                $header->sort = 'fuzzy-numeric';
                $header->title = 'Digital Population';
                $header->desc = 'The Digital Population is based on internet penetration in the country.';
                break;
            case 'digital-population-health':
                $header->sort = 'traffic-light';
                $header->title = 'Percent of Digital Population';
                $header->desc = 'Target Audience as a percent of the Digital Population based on internet penetration in the country.';
	            break;
            case Model_Presence::METRIC_POPULARITY_PERCENT:
                $header->desc = 'Percent of target audience shows the current audience as a percentage against the target audience.';
                break;
            case Model_Presence::METRIC_POPULARITY_TIME:
                $header->desc = 'Time to target audience shows the calculated time it will take to reach the target audience given the current trend in gains of followers / fans per day.';
                break;
            case Model_Presence::METRIC_POSTS_PER_DAY:
                $header->desc = 'Actions per day measures the average number of posts, comments and other actions per day.';
                break;
            case Model_Presence::METRIC_RESPONSE_TIME:
                $header->desc = 'Response time measures the average time it takes to reply to a post or tweet';
                break;
            case 'presences':
                $header->title = 'Presences';
                break;
            case 'options':
				$header->sort = null;
                $header->width = '100px';
                break;
            case 'compare':
                $header->sort = 'checkbox';
                $header->title = '<span class="icon-check"></span>';
                $header->desc = 'Select all the presences that you would like to compare, and then click on the Compare Button above';
                break;
            case 'handle':
                $header->title = 'Handle';
                break;
            case 'sign-off':
                $header->sort = 'data-value-numeric';
                $header->title = 'Sign-Off';
                $header->desc = 'Sign Off shows whether a presence has been signed off by the Head of Digital.';
                break;
            case 'branding':
                $header->sort = 'data-value-numeric';
                $header->title = 'Branding';
                $header->desc = 'Branding shows whether a presence meets the British Council branding guidelines for social media presences.';
                break;
            default:
                return null;
        }
        return $header;
    }

    public static function tableIndexHeaders() {

        return array();
    }

    public static function generateTableHeaders(){
        $return = array();
        foreach(static::tableIndexHeaders() as $type =>  $csv){
            $return[] = self::tableHeader($type,$csv);
        }
        return $return;
    }

	public function badgeInformation($badgeData, $badge)
	{
		$score = round($badgeData[$badge->getName()]);

		$colors = $this->view->geochartMetrics[$badge->getName()];
		$color = $colors->colors[0];
		foreach($colors->range as $i => $value){
			if($score >= $value) $color = $colors->colors[$i];
		}

		$badgeArr = array();
		$badgeArr["title"] = $badge->getTitle();
		$badgeArr["rank"] = array(
			"value" => $badgeData[$badge->getName()."_rank"], //get rank for badge
			"denominator" => $badgeData['denominator'] //get count of $model type
		);
		$badgeArr['score'] = array(
			"value"	=> $score, //get score badge
			"color" => $color //get colour for this score
		);
		if(count($badge->getMetrics()) > 0){
			$metrics = array();
			foreach($badge->getMetrics() as $metric){
				$metrics[] = array(
					"title" => $metric::getTitle(),
					"icon" => $metric::getIcon()
				);
			}
			$badgeArr['metrics'] = $metrics;
		}
		return $badgeArr;
	}

	public function badgeDetails($badgeData)
	{
		$badgeArr = array();
		$badges = Badge_Factory::getBadges();

		//get total and handle it separately
		$total = $badges[Badge_Total::getName()];

		$badgeArr['main'] = $this->badgeInformation($badgeData, $total);

		$small = array();
		foreach($badges as $badge){
			if($badge instanceof Badge_Total) continue;
			$small[] = $this->badgeInformation($badgeData, $badge);
		}
		$badgeArr['small'] = $small;

		return $badgeArr;
	}

}