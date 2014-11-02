<?php

class IndexController extends GraphingController
{
	protected static $publicActions = array('index', 'build-badge-data');

	public function indexAction()
	{
		$dayRange = 30;
		$now = new DateTime();
		$old = clone $now;
		$old->modify("-$dayRange days");

		$this->view->title = 'British Council Social Media Monitor';
		$this->view->titleIcon = 'icon-home';
		$this->view->countries = Model_Country::fetchAll();

		list($mapData, $smallMapData, $groupData) = $this->getCacheableData($dayRange, true);

		$smallCountries = array();
		foreach(Model_Country::smallCountryCodes() as $code => $country) {
			$smallCountry = Model_Country::fetchByCountryCode($code);
			if($smallCountry) {
                $smallCountries[] = $smallCountry;
            }
		}

        $this->view->mapArgs = array(
            'geochartMetrics' => $this->view->geochartMetrics,
            'mapData' => $mapData,
            'smallMapData' => $smallMapData,
            'groupData' => $groupData
        );
		$this->view->dateRangeString = $old->format('d M Y') . ' - ' . $now->format('d M Y');
		$this->view->currentDate = $now->format('Y-m-d');
		$this->view->dayRange = $dayRange;
		$this->view->badges = Badge_Factory::getBadges();
		$this->view->smallCountries = $smallCountries;
		$this->view->groups = Model_Group::fetchAll();
	}

	public function dateRangeAction()
	{
		$dateRange = $this->_request->getParam('dateRange');

		if (count($dateRange) == 2 && $dateRange[0] && $dateRange[1]) {
			$_SESSION['dateRange'] = $dateRange;
			$this->apiSuccess($dateRange);
		} else {
			$this->apiError('Invalid date range');
		}
	}

	public function downloadimageAction()
	{
		$svg = base64_decode($this->_request->getParam('svg'));

		$basepath = tempnam(sys_get_temp_dir(), 'chart_');
		$svgpath = $basepath . '.svg';
		file_put_contents($svgpath, $svg);

		switch ($this->_request->getParam('type')) {
			case 'png' :
				$pngpath = $basepath . '.png';
				$output = '';
				$paths = 'convert ' . $svgpath . ' ' . $pngpath;
				exec($paths, $output);
				unlink($svgpath);

				$fileName = basename($pngpath);
				break;
			case 'svg' :
			default:
				$fileName = basename($svgpath);
		}

		$this->_helper->json(array(
			'filename' => $fileName
		));

	}

	protected function getCacheableData($dayRange, $temp) {
		$allBadgeTypes = Badge_Factory::getBadgeNames();

		$key = 'badge_data_'.$dayRange;
		$badgeData = self::getObjectCache($key, $temp);
		if(!$badgeData){
			//todo include week data in the data that we send out as json
			$badgeData = Badge_Factory::getAllCurrentData(
				Badge_Period::MONTH(),
				new \DateTime("now -$dayRange days"),
				new \DateTime('now')
			);
			self::setObjectCache($key, $badgeData, $temp);
		}

		$key = 'map_data_' . $dayRange;
		$mapData = self::getObjectCache($key, $temp);
		if (!$mapData) {
			$mapData = Model_Country::constructFrontPageData($badgeData, $dayRange);

			$existingCountries = array();
			foreach($mapData as $country){
				$existingCountries[$country->c] = $country->n;
			}

			// construct a set of data for a country that has no presence
			$blankBadges = new stdClass();
			foreach ($allBadgeTypes as $badgeType) {
				$blankBadges->{$badgeType} = array();
				for ($day = 1; $day <= $dayRange; $day++) {
					$blankBadges->{$badgeType}[$day] = (object)array('s'=>0,'l'=>'N/A');
				}
			}

            $missingCountries = array_diff_key(Model_Country::countryCodes(), $existingCountries);
			foreach($missingCountries as $code => $name){
				$mapData[] = (object)array(
					'id'=>-1,
					'c' => $code,
					'n' => $name,
					'p' => 0,
					'b' => $blankBadges
				);
			}

			self::setObjectCache($key, $mapData, $temp);
		}

		$key = 'small_country_data_' . $dayRange;
		$smallMapData = self::getObjectCache($key, $temp);
		if (!$smallMapData) {
			$smallCountries = Model_Country::smallCountryCodes();
			$smallMapData = array();
			foreach($mapData as $country){
				if(array_key_exists($country->c, $smallCountries)){
					$smallMapData[] = $country;
				}
			}
			self::setObjectCache($key, $smallMapData, $temp);
		}

		$key = 'group_data_' . $dayRange;
		$groupData = self::getObjectCache($key, $temp);
		if (!$groupData) {
			$groupData = Model_Group::constructFrontPageData($badgeData, $dayRange);
			self::setObjectCache($key, $groupData, $temp);
		}

		return array($mapData, $smallMapData, $groupData);
	}

	/**
	 * Ensures that the last 60 days worth of badge data is populated
	 */
	public function buildBadgeDataAction()
	{
		// make sure that the last 60 day's worth of badge_history data exists
		Badge_Factory::guaranteeHistoricalData(Badge_Period::MONTH(), new \DateTime('now -60 days'), new \DateTime('now'), true);

		// do everything that the index page does, but using the (potentially) updated data
		$this->getCacheableData(30, false);

		// also store a non-temporary version of Badge::badgeData
		$key = 'presence_badges';
        $oldData = self::getObjectCache($key, false);
        if(!$oldData) {
            //if no oldData (too old or temp) get current data (which is now up to date) and set it in the object cache
            $data = Badge_Factory::getAllCurrentData(Badge_Period::MONTH(), new DateTime(), new DateTime());
            self::setObjectCache($key, $data, false);
        }

//		Badge_Factory::getAllCurrentData(Badge_Period::WEEK(), new DateTime(), new DateTime()); //todo: uncomment this when it is needed
		exit;

	}

	public function servefileAction()
	{
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$fileName = $this->_request->getParam('fileName');
		$fileType = pathinfo($fileName, PATHINFO_EXTENSION);
		$niceName = $this->_request->getParam('nicename') . '.' . $fileType;

		// simple validation;
		if (strpos($fileName, '..') > -1) exit;
		if (!in_array($fileType, array('png', 'svg'))) exit;

		header("Content-Type: image/" . $fileType . "\n");
		header("Content-Disposition: attachment; filename=" . $niceName);

		$fileName = sys_get_temp_dir() . $fileName;
		// echo the content to the client browser
		readfile($fileName);

		// delete the temp files
		unlink($fileName);
		exit;
	}


}

