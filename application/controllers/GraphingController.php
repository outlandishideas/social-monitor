<?php


class GraphingController extends BaseController {

	protected function graphs(Model_Presence $presence) {
		$graphs = array();
		$graphs[] = (object)array(
			'metric' => 'popularity',
			'yAxisLabel' => ($presence->isForFacebook() ? 'Fans' : 'Followers') . ' gained per day',
			'title' => 'Audience Rate'
		);
		$graphs[] = (object)array(
			'metric' => 'posts_per_day',
			'yAxisLabel' => 'Posts per day',
			'title' => 'Posts Per Day'
		);
		$graphs[] = (object)array(
			'metric' => 'response_time',
			'yAxisLabel' => 'Response time (hours)',
			'title' => 'Average Response Time (hours)'
		);
		foreach ($graphs as $g) {
			$g->presence_id = $presence->id;
		}
		return $graphs;
	}

}