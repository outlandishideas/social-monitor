<?php

class IndexController extends BaseController
{
	public function indexAction() {
		$this->view->title = 'Home';
		$this->view->countries = Model_Country::fetchAll();
        $this->view->jsonCountries = $this->campaignsToJson();
	}

    public function campaignsToJson(){
        $countries = Model_Country::fetchAll();
        $json = array();
        foreach($countries as $country){
            $kpis = $country->getKpiData();
            $name = $country->getNameFromCode($country->country);
            $json[$name] = array('name' =>$name, 'id'=>$country->id, 'target' => $country->audience/2, 'kpis' => $kpis);
        }
        return json_encode($json);
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

