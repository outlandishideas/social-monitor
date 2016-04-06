<?php

class IndexController extends GraphingController
{
	protected static $publicActions = array('index');

	public function indexAction()
	{
		$dayRange = 30;
		$now = new DateTime();
		$old = clone $now;
		$old->modify("-$dayRange days");

		$this->view->pdfLink = $this->getContainer()->get('kpi_download_linker')->link();

		$objectCacheManager = $this->getContainer()->get('object-cache-manager');
		list($mapData, $groupData, $fanData) = $objectCacheManager->getFrontPageData($dayRange, true);

        $this->view->mapArgs = array(
            'geochartMetrics' => $this->view->geochartMetrics,
            'mapData' => $mapData,
            'groupData' => $groupData,
            'fanData' => $fanData,
			'totalPresences' => count(Model_PresenceFactory::getPresences())
        );

		$this->view->currentDate = $now->format('Y-m-d');
		$this->view->dayRange = $dayRange;
		$this->view->badges = Badge_Factory::getBadges();
		$this->view->groups = Model_Group::fetchAll();
		$this->view->joyride = $this->getContainer()->get('joyride.home');
	}

	public function dateRangeAction()
	{
		$dateRange = $this->_request->getParam('dateRange');

		if (count($dateRange) == 2 && $dateRange[0] && $dateRange[1]) {
			$_SESSION['dateRange'] = $dateRange;
			$this->apiSuccess($dateRange);
		} else {
			$this->apiError($this->translator->trans('Error.invalid-date-range'));
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

