<?php


abstract class GraphingController extends BaseController {

	const METRIC_POPULARITY = 'popularity';
	const METRIC_POSTS_PER_DAY = 'posts_per_day';
	const METRIC_RESPONSE_TIME = 'response_time';

	protected function graphMetrics() {
		return array(
			self::METRIC_POPULARITY=>'Audience Rate',
			self::METRIC_POSTS_PER_DAY=>'Posts Per Day',
			self::METRIC_RESPONSE_TIME=>'Response Time'
		);
	}

	protected function graphs(Model_Presence $presence) {
		$graphs = array();
		$graphs[] = (object)array(
			'metric' => self::METRIC_POPULARITY,
			'yAxisLabel' => ($presence->isForFacebook() ? 'Fans' : 'Followers') . ' gained per day',
			'title' => 'Audience Rate'
		);
		$graphs[] = (object)array(
			'metric' => self::METRIC_POSTS_PER_DAY,
			'yAxisLabel' => 'Posts per day',
			'title' => 'Posts Per Day'
		);
		$graphs[] = (object)array(
			'metric' => self::METRIC_RESPONSE_TIME,
			'yAxisLabel' => 'Response time (hours)',
			'title' => 'Average Response Time (hours)'
		);
		foreach ($graphs as $g) {
			$g->presence_id = $presence->id;
		}
		return $graphs;
	}

}