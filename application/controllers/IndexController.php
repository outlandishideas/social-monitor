<?php

class IndexController extends GraphingController
{
	protected $publicActions = array('index', 'country-stats', 'build-badge-data');

	public function indexAction() {
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
		$this->view->mapData = Model_Country::generateMapData($dayRange);
		$this->view->metricOptions = $metrics;
		$this->view->dateRangeString = $old->format('d M Y') .' - '. $now->format('d M Y');
		$this->view->currentDate = $now->format('Y-m-d');
		$this->view->dayRange = $dayRange;
	}

	public function dateRangeAction() {
		$dateRange = $this->_request->dateRange;

		if (count($dateRange) == 2 && $dateRange[0] && $dateRange[1]) {
			$_SESSION['dateRange'] = $dateRange;
			$this->apiSuccess($dateRange);
		} else {
			$this->apiError('Invalid date range');
		}
	}

	public function countryStatsAction() {
		$country = Model_Country::fetchById($this->_request->id);
		if ($country) {
			$this->view->country = $country;
			$this->view->metric = $this->_request->metric;
		} else {
			$this->_helper->viewRenderer->setNoRender(true);
		}
		$this->_helper->layout()->disableLayout();
	}

	public function downloadimageAction () {
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

	public function buildBadgeDataAction () {
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$end = new DateTime('now');
		$start = new DateTime('now -30 days');
		Model_Badge::getAllData('month', $start, $end);
//		Model_Badge::getAllData('week', $start, $end); //todo: uncomment this when it is needed
		exit;
	}

	public function servefileAction () {
		$this->_helper->layout()->disableLayout();
		$this->_helper->viewRenderer->setNoRender(true);

		$fileName = $this->_request->fileName;
		$fileType = pathinfo($fileName, PATHINFO_EXTENSION);
		$niceName = $this->_request->nicename.'.'.$fileType;

		// simple validation;
		if (strpos($fileName, '..') > -1) exit;
		if (!in_array($fileType, array('png', 'svg'))) exit;

		header("Content-Type: image/" . $fileType . "\n");
		header("Content-Disposition: attachment; filename=".$niceName);

		$fileName = sys_get_temp_dir() . $fileName;
		// echo the content to the client browser
		readfile($fileName);

		// delete the temp files
		unlink($fileName);
		exit;
	}


}

