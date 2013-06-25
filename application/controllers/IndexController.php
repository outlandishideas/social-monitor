<?php

class IndexController extends GraphingController
{
    protected $publicActions = array('index', 'country-stats');

	public function indexAction() {
		/** @var Model_Country[] $countries */
		$countries = Model_Country::fetchAll();
		$kpiData = array();
		$metrics = self::mapMetrics();
        //get the Badge Data from the presence_history table, or create ourselves if it doesn't exist
        $data = Model_Country::getBadgeData();

        //take the raw data and organise it depending on how it will be used
        $badgeData = Model_Country::organizeBadgeData($data);
		foreach($countries as $country){
			$row = array(
				'country' => $country->country,
				'name' => $country->display_name,
				'id'=>intval($country->id),
				'targetAudience' => $country->getTargetAudience(),
				'presenceCount' => count($country->getPresences())
			);
            $totalScore = 0;
			foreach ($badgeData as $key=>$badge) {
                $score = isset($badge->score[intval($country->id)]) ? $badge->score[intval($country->id)] : 0;
                $totalScore += $score;
				$row[$key] = array(
					'average'=> $score,
					'label'=> round($score).'%' //$this->view->trafficLight()->label($badge, $key)
				);
			}
            $row['total'] = array(
                'average'=>$score/count($badgeData),
                'label' => round($score/count($badgeData)).'%'
            );

			$kpiData[] = $row;
		}

		$this->view->title = 'British Council Social Media Monitor';
		$this->view->titleIcon = 'icon-home';
		$this->view->countries = Model_Country::fetchAll();
        $this->view->kpiData = $kpiData;
		$this->view->metricOptions = $metrics;
        $now = new DateTime();
        $old = new DateTime('-1 month');
        $this->view->currentDate = $now;
        $this->view->oldDate = $old;
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

