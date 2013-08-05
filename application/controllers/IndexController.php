<?php

class IndexController extends GraphingController
{
	protected static $publicActions = array('index', 'country-stats', 'build-badge-data');

	public function indexAction()
	{
		$dayRange = 30;
		$now = new DateTime();
		$old = clone $now;
		$old->modify("-$dayRange days");

		$metrics = array();
		foreach (Model_Badge::$ALL_BADGE_TYPES as $type) {
			$metrics[$type] = Model_Badge::badgeTitle($type);
		}
		$this->view->title = 'British Council Social Media Monitor';
		$this->view->titleIcon = 'icon-home';
		$this->view->countries = Model_Country::fetchAll();

		$key = 'badge_data_'.$dayRange;
		$badgeData = self::getObjectCache($key);
		if(!$badgeData){
			$endDate = new DateTime('now');
			$startDate = new DateTime("now -$dayRange days");

			//todo include week data in the data that we send out as json
			$badgeData = Model_Badge::getAllData('month', $startDate, $endDate);
			self::setObjectCache($key, $badgeData);
		}

		$key = 'map_data_' . $dayRange;
		$mapData = self::getObjectCache($key);
		if (!$mapData) {
			$mapData = Model_Country::constructFrontPageData($badgeData, $dayRange);
			self::setObjectCache($key, $mapData);
		}

		$key = 'group_data_' . $dayRange;
		$groupData = self::getObjectCache($key);
		if (!$groupData) {
			$groupData = Model_Group::constructFrontPageData($badgeData, $dayRange);
			$badgeTypes = Model_Badge::$ALL_BADGE_TYPES;
			/** @var Zend_View_Helper_TrafficLight $trafficLight */
			$trafficLight = $this->view->trafficLight();
			foreach ($groupData as $group) {
				foreach ($badgeTypes as $type) {
					foreach ($group->b[$type] as $value) {
						$value->c = $trafficLight->color($value->s, $type);
					}
				}
			}
			self::setObjectCache($key, $groupData);
		}

		$this->view->mapData = $mapData;
		$this->view->groupData = $groupData;
		$this->view->metricOptions = $metrics;
		$this->view->dateRangeString = $old->format('d M Y') . ' - ' . $now->format('d M Y');
		$this->view->currentDate = $now->format('Y-m-d');
		$this->view->dayRange = $dayRange;
	}

	public function dateRangeAction()
	{
		$dateRange = $this->_request->dateRange;

		if (count($dateRange) == 2 && $dateRange[0] && $dateRange[1]) {
			$_SESSION['dateRange'] = $dateRange;
			$this->apiSuccess($dateRange);
		} else {
			$this->apiError('Invalid date range');
		}
	}

	public function countryStatsAction()
	{
		/** @var Model_Country $country */
        if($this->_request->model == 'group'){
            $campaign = Model_Group::fetchById($this->_request->id);
        } else {
            $campaign = Model_Country::fetchById($this->_request->id);
        }

		if ($campaign) {
			$date = new DateTime();
			$badgeData = array();
			foreach ($campaign->getPresences() as $presence) {
				$badgeData[$presence->id] = $presence->badges();
			}

			$this->view->campaign = $campaign;
			$this->view->metric = $this->_request->metric;
			$this->view->badgeData = $badgeData;
			$this->view->date = $date;
            $this->view->model = $this->_request->model;
		} else {
			$this->_helper->viewRenderer->setNoRender(true);
		}
		$this->_helper->layout()->disableLayout();
	}

	public function downloadimageAction()
	{
		$svg = base64_decode($this->_request->svg);

		$basepath = tempnam(sys_get_temp_dir(), 'chart_');
		$svgpath = $basepath . '.svg';
		file_put_contents($svgpath, $svg);

		switch ($this->_request->type) {
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

	/**
	 * Ensures that the last 60 days worth of badge data is populated
	 */
	public function buildBadgeDataAction()
	{
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$end = new DateTime('now');
		$start = new DateTime('now -60 days');
		Model_Badge::getAllData('month', $start, $end);
//		Model_Badge::getAllData('week', $start, $end); //todo: uncomment this when it is needed
		exit;
	}

	public function servefileAction()
	{
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$fileName = $this->_request->fileName;
		$fileType = pathinfo($fileName, PATHINFO_EXTENSION);
		$niceName = $this->_request->nicename . '.' . $fileType;

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

