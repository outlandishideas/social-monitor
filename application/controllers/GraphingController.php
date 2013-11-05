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
        $return = null;
        $metrics = self::tableMetrics();
        switch($type){
            case('name'):
                $return = (object)array(
                    'name' => 'name',
                    'sort' => 'auto',
                    'title' => 'Name',
                    'desc' => 'This is a description for name'
                );
                break;
            case('country'):
                $return = (object)array(
                    'name' => 'country',
                    'sort' => 'auto',
                    'title' => 'Country',
                    'desc' => 'This is a description for country'
                );
                break;
            case('total-rank'):
                $return = (object)array(
                    'name' => 'total-rank',
                    'sort' => 'numeric',
                    'title' => 'Global Rank',
                    'desc' => 'This is a description for total rank'
                );
                break;
            case('total-score'):
                $return = (object)array(
                    'name' => 'total-score',
                    'sort' => 'numeric',
                    'title' => 'Overall Score',
                    'desc' => 'This is a description for total score'
                );
                break;
            case('current-audience'):
                $return = (object)array(
                    'name' => 'current-audience',
                    'sort' => 'fuzzy-numeric',
                    'title' => 'Audience',
                    'desc' => 'This is a description for current audience'
                );
                break;
            case('target-audience'):
                $return = (object)array(
                    'name' => 'target-audience',
                    'sort' => 'fuzzy-numeric',
                    'title' => 'Target Audience',
                    'desc' => 'This is a description for target audience'
                );
                break;
            case(Model_Presence::METRIC_POPULARITY_PERCENT):
                $return = (object)array(
                    'name' => Model_Presence::METRIC_POPULARITY_PERCENT,
                    'sort' => 'traffic-light',
                    'width' => '150px',
                    'title' => $metrics[Model_Presence::METRIC_POPULARITY_PERCENT],
                    'desc' => 'This is a description for popularity percent'
                );
                break;
            case(Model_Presence::METRIC_POPULARITY_TIME):
                $return = (object)array(
                    'name' => Model_Presence::METRIC_POPULARITY_TIME,
                    'sort' => 'traffic-light',
                    'width' => '150px',
                    'title' => $metrics[Model_Presence::METRIC_POPULARITY_TIME],
                    'desc' => 'This is a description for popularity time'
                );
                break;
            case(Model_Presence::METRIC_POSTS_PER_DAY):
                $return = (object)array(
                    'name' => Model_Presence::METRIC_POSTS_PER_DAY,
                    'sort' => 'traffic-light',
                    'width' => '150px',
                    'title' => $metrics[Model_Presence::METRIC_POSTS_PER_DAY],
                    'desc' => 'This is a description for actions per day'
                );
                break;
            case(Model_Presence::METRIC_RESPONSE_TIME):
                $return = (object)array(
                    'name' => Model_Presence::METRIC_RESPONSE_TIME,
                    'sort' => 'traffic-light',
                    'width' => '150px',
                    'title' => $metrics[Model_Presence::METRIC_RESPONSE_TIME],
                    'desc' => 'This is a description for response time'
                );
                break;
            case('presences'):
                $return = (object)array(
                    'name' => 'presences',
                    'title' => 'Presences',
                    'desc' => 'This is a description for presences'
                );
                break;
            case('options'):
                $return = (object)array(
                    'name' => 'options',
                    'desc' => 'This is a description for options'
                );
                break;
            case('compare'):
                $return = (object)array(
                    'name' => 'compare',
                    'sort' => 'checkbox',
                    'title' => '<span class="icon-check"></span>',
                    'desc' => 'This is a description for compare'
                );
                break;
            case('handle'):
                $return = (object)array(
                    'name' => 'handle',
                    'sort' => 'auto',
                    'title' => 'Handle',
                    'desc' => 'This is a description for options'
                );
                break;
            case('sign-off'):
                $return = (object)array(
                    'name' => 'sign-off',
                    'sort' => 'data-value-numeric',
                    'title' => 'Sign-Off',
                    'desc' => 'This is a description for sign off'
                );
                break;
            case('branding'):
                $return = (object)array(
                    'name' => 'branding',
                    'sort' => 'data-value-numeric',
                    'title' => 'Branding',
                    'desc' => 'This is a description for sign off'
                );
                break;
            default:
                return null;
        }
        $return->csv = $csv;
        return $return;
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