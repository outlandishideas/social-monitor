<?php

class IndexController extends GraphingController
{
    protected $publicActions = array('index', 'country-stats');

	public function indexAction() {
		/** @var Model_Country[] $countries */
		$countries = Model_Country::fetchAll();
		$kpiData = array();
		$metrics = self::mapMetrics();

		$this->view->title = 'British Council Social Media Monitor';
		$this->view->titleIcon = 'icon-home';
		$this->view->countries = Model_Country::fetchAll();
        $this->view->mapData = Model_Country::mapDataFactory();
		$this->view->metricOptions = $metrics;
        $now = new DateTime();
        $old = new DateTime('-1 month');
        $this->view->currentDate = $now;
        $this->view->oldDate = $old;
        $this->view->dateRangeString = $old->format('d-M-Y') .' - '. $now->format('d-M-Y');
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

