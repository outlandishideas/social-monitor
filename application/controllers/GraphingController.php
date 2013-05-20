<?php


abstract class GraphingController extends BaseController {

	protected static function graphMetrics() {
		return array(
			Model_Presence::METRIC_POPULARITY_RATE => 'Audience Rate',
			Model_Presence::METRIC_POSTS_PER_DAY => 'Posts Per Day',
			Model_Presence::METRIC_RESPONSE_TIME => 'Response Time',
			Model_Presence::METRIC_LINK_RATIO => 'Links Ratio'
		);
	}

	protected static function mapMetrics(){
		return array(
			Model_Presence::METRIC_POPULARITY_PERCENT => 'Percent of Target Audience',
			Model_Presence::METRIC_POPULARITY_TIME => 'Time to Reach Target Audience',
			Model_Presence::METRIC_POSTS_PER_DAY => 'Average Number of Posts Per Day',
			Model_Presence::METRIC_RESPONSE_TIME => 'Average Response Time',
			Model_Presence::METRIC_LINK_RATIO => 'Average Links Ratio'
		);
	}

	protected static function tableMetrics(){
		return array(
			Model_Presence::METRIC_POPULARITY_PERCENT => 'Percent of Target Audience',
			Model_Presence::METRIC_POPULARITY_TIME => 'Time to Reach Target Audience',
			Model_Presence::METRIC_POSTS_PER_DAY => 'Average Number of Posts Per Day',
			Model_Presence::METRIC_RESPONSE_TIME => 'Average Response Time',
			Model_Presence::METRIC_LINK_RATIO => 'Average Links Ratio'
		);
	}

	protected function graphs(Model_Presence $presence) {
		$graphs = array();
		$graphs[] = (object)array(
			'metric' => Model_Presence::METRIC_POPULARITY_RATE,
			'yAxisLabel' => ($presence->isForFacebook() ? 'Fans' : 'Followers') . ' gained per day',
			'title' => 'Audience Rate'
		);
		$graphs[] = (object)array(
			'metric' => Model_Presence::METRIC_POSTS_PER_DAY,
			'yAxisLabel' => 'Posts per day',
			'title' => 'Posts Per Day'
		);
		$graphs[] = (object)array(
			'metric' => Model_Presence::METRIC_RESPONSE_TIME,
			'yAxisLabel' => 'Response time (hours)',
			'title' => 'Average Response Time (hours)'
		);
		$graphs[] = (object)array(
			'metric' => Model_Presence::METRIC_LINK_RATIO,
			'yAxisLabel' => 'Link percent',
			'title' => 'Links to BC sites (%)'
		);
		foreach ($graphs as $g) {
			$g->presence_id = $presence->id;
		}
		return $graphs;
	}

	public function init() {
		parent::init();

		$colors = (object)array(
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

		$metrics[Model_Presence::METRIC_LINK_RATIO] = (object)array(
			'range' => array(0, 50, 100),
			'colors' => array($colors->red, $colors->yellow, $colors->green)
		);

		foreach (self::mapMetrics() as $key=>$label) {
			$metrics[$key]->label = $label;
		}

		// convert each hex string to rgb values
		foreach ($metrics as $args) {
			$args->colorsRgb = array();
			foreach ($args->colors as $color) {
				$rgb = array();
				for ($i=1; $i<6; $i+=2) {
					$rgb[] = hexdec(substr($color, $i, 2));
				}
				$args->colorsRgb[] = $rgb;
			}
		}
		$this->view->trafficMetrics = $metrics;
	}


}