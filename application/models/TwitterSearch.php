<?php

class Model_TwitterSearch extends Model_TwitterBase {
	protected $_tableName = 'twitter_searches';
	protected $type = 'search';

	public function getLabel() {
		return $this->query;
	}
	
	public function getFetchUrl() {
		$this->fetchUrl = 'search/tweets';
		return $this->fetchUrl;
	}

	public function getFetchArgsArray() {
		$fetchArgs = array(
			'q' => $this->query,
			'result_type' => 'recent',
			'include_entities' => 1,
			'count'=>Zend_Registry::get('config')->twitter->search_fetch_per_page
		);
		if ($this->geoArgs) {
			$this->fetchArgsArray = array();
			foreach ($this->geoArgs->areas as $area) {
				$args = $fetchArgs;
				$args['geocode'] = implode(',', array(
					$area->lat,
					$area->lon,
					$area->radius . 'km'
				));
				$this->fetchArgsArray[] = $args;
			}
		} else {
			$this->fetchArgsArray = array($fetchArgs);
		}
		return $this->fetchArgsArray;
	}

	protected function getTweetListFromApiResult($apiResult) {
		return $apiResult->statuses;
	}

	public function getGeoArgs() {
		$args = $this->geo_args_json;
		return ($args ? json_decode($args) : null);
	}

	public function fromArray($data) {
		if (!empty($data['geocode'])) {
			$areas = array();
			foreach ($data['lat'] as $i=>$lat) {
				$lon = $data['lon'][$i];
				$radius = $data['rad'][$i];
				$areas[] = array(
					'lat' => $lat,
					'lon' => $lon,
					'radius' => $radius
				);
			}

			unset($data['geocode']);
			unset($data['lat']);
			unset($data['lon']);
			unset($data['rad']);

			$geoArgs = array('areas'=>$areas);
			$data['geo_args_json'] = json_encode($geoArgs);
		} else if (!isset($data['geo_args_json'])) {
			$data['geo_args_json'] = null;
		}
		parent::fromArray($data);
	}
}