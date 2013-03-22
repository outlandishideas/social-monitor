<?php

class Model_Region extends Model_Base {
	protected $_tableName = 'regions', $_sortColumn = 'id';

	public function getGeoArgs() {
		$args = $this->args_json;
		return ($args ? json_decode($args) : null);
	}

	public function fromArray($data) {
		if (isset($data['lat'])) {
			// If building from a submitted form, wrap the lat, lon and rad args into a json blob
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

			unset($data['lat']);
			unset($data['lon']);
			unset($data['rad']);

			$geoArgs = array('areas'=>$areas);
			$data['args_json'] = json_encode($geoArgs);
		}

		parent::fromArray($data);
	}
}
