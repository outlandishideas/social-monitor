<?php


abstract class GraphingController extends BaseController {

	protected static function graphMetrics() {
		return array(
			Model_Presence::METRIC_POPULARITY_RATE => 'Audience Rate',
			Model_Presence::METRIC_POSTS_PER_DAY => 'Actions Per Day',
			Model_Presence::METRIC_RESPONSE_TIME => 'Response Time',
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

	protected function graphs(Model_Presence $presence) {
		$graphs = array();
		$graphs[] = (object)array(
			'metric' => Model_Presence::METRIC_POPULARITY_RATE,
			'yAxisLabel' => ($presence->isForFacebook() ? 'Fans' : 'Followers') . ' gained per day',
			'title' => 'Gains in Followers / Fans per day'
		);
		$graphs[] = (object)array(
			'metric' => Model_Presence::METRIC_POSTS_PER_DAY,
			'yAxisLabel' => 'Posts per day',
			'title' => 'Actions Per Day'
		);
		$graphs[] = (object)array(
			'metric' => Model_Presence::METRIC_RESPONSE_TIME,
			'yAxisLabel' => 'Response time (hours)',
			'title' => 'Average Response Time (hours)'
		);
		foreach ($graphs as $g) {
			$g->presence_id = $presence->id;
		}
		return $graphs;
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

}