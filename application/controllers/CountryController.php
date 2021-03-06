<?php

use Outlandish\SocialMonitor\Report\ReportableCountry;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\Header\Name;
use Outlandish\SocialMonitor\Validation;

class CountryController extends CampaignController {

    protected static $publicActions = array('stats-panel', 'report');

	protected $formInputLabels = array(
		'display_name' => 'route.country.edit.label.display-name',
		'country' => 'route.country.edit.label.country',
		'audience' => 'route.country.edit.label.audience-size',
		'population' => 'route.country.edit.label.country-population',
		'penetration' => 'route.country.edit.label.internet-penetration',
        'page_title' => 'route.region.edit-all.page-title',
	);

	/**
	 * @param bool $validate
	 * @return Model_Country
	 */
	protected function getRequestedCountry($validate = true)
	{
		$country = Model_Country::fetchById($this->_request->getParam('id'));
		if ($validate) {
			$this->validateData($country);
		}
		return $country;
	}

	/**
	 * Lists all countries
	 * @user-level user
	 */
	public function indexAction() {
		$objectCacheManager = $this->getContainer()->get('object-cache-manager');
		$table = $objectCacheManager->getCountriesTable();

		/** @var Model_Country[] $countries */
		$countries = $table->getTableData();
		$presences = array();
		foreach (Model_PresenceFactory::getPresences() as $p) {
			$presences[$p->id] = $p;
		}
		$query = self::db()->prepare('SELECT c.id, cp.presence_id FROM campaigns AS c LEFT OUTER JOIN campaign_presences AS cp ON c.id = cp.campaign_id');
		$query->execute();
		$mapping = array();
		foreach ($query->fetchAll(PDO::FETCH_OBJ) as $row) {
			if (!isset($mapping[$row->id])) {
				$mapping[$row->id] = array();
			}
			$mapping[$row->id][] = $row->presence_id;
		}
		foreach ($countries as $country) {
			$country->getPresences($mapping, $presences);
		}

		$rows = $objectCacheManager->getCountryIndexRows($this->_request->getParam('force'));

		$this->view->countries = $countries;
		$this->view->rows = $rows;
        $this->view->tableHeaders = $table->getHeaders();
        $this->view->sortCol = Name::NAME;
	}

    public function statsPanelAction()
    {
        $id = $this->_request->getParam('id');
        $country = $id ? Model_Country::fetchById($id) : null;
        if(!$country){
            $this->_helper->viewRenderer->setNoRender(true);
        } else {
            $this->view->country = $country;
        }
        $this->_helper->layout()->disableLayout();
    }

    /**
	 * Views a specific country
	 * @user-level user
	 */
	public function viewAction()
	{
		$country = $this->getRequestedCountry();

		$this->view->titleIcon = Model_Country::ICON_TYPE;
		$this->view->badgePartial = $this->badgeDetails($country);
		$this->view->chartOptions = self::chartOptions();
        $this->view->country = $country;
		$this->updatePageTitle(['country' => $country->display_name]);
        $this->view->allCampaigns = Model_Country::fetchAll();
		$this->view->pdfLink = $this->getContainer()->get('kpi_download_linker')->link();
	}

	/**
	 * download a report for a specific country
	 * @user-level user
	 */
	public function downloadReportAction()
	{
		$country = $this->getRequestedCountry();

		//if we don't have a now parameter create a DateTime now
		//else create a date from the now parameter
		$to = date_create_from_format("Y-m-d", $this->_request->getParam('to'));
		if (!$to) {
			$to = new DateTime();
		}

		//if we don't have a then parameter generate a default then from $now
		//else create a date from the then parameter
		$from = date_create_from_format("Y-m-d", $this->_request->getParam('from'));
		if(!$from) {
			$from = clone $to;
			$from->modify('-30 days');
		}

		//if $now is earlier than $then then reverse them.
		if ($to->getTimestamp() <= $from->getTimestamp()) {
			$oldThen = clone $from;
			$from = clone $to;
			$to = clone $oldThen;
		}

		$downloader = $this->getContainer()->get('report.downloader');

		$url = $downloader->getUrl(new ReportableCountry($country, $this->translator), $from, $to);

		do {
			$content = @file_get_contents($url);
		} while(empty($content));

		header('Content-type: application/pdf');
		header('Content-Disposition: attachment; filename=report.pdf');
		echo $content;
		exit;
	}


	/**
	 * generates a report in html to turned into pdf by other server
	 */
	public function reportAction()
	{
		$country = $this->getRequestedCountry();

		//if we don't have a now parameter create a DateTime now
		//else create a date from the now parameter
		$to = date_create_from_format("Y-m-d", $this->_request->getParam('to'));
		if (!$to) {
			$to = new DateTime();
		}

		//if we don't have a then parameter generate a default then from $now
		//else create a date from the then parameter
		$from = date_create_from_format("Y-m-d", $this->_request->getParam('from'));
		if(!$from) {
			$from = clone $to;
			$from->modify('-30 days');
		}

		//if $now is earlier than $then then reverse them.
		if ($to->getTimestamp() <= $from->getTimestamp()) {
			$oldThen = clone $from;
			$from = clone $to;
			$to = clone $oldThen;
		}

		$report = (new ReportGenerator())->generate(new ReportableCountry($country, $this->translator), $from, $to);
		$report->generate();
		$this->view->report = $report;
		$this->view->country = $country;
		$this->_helper->layout()->setLayout('report');

	}

	/**
	 * Creates a new country
	 * @user-level manager
	 */
	public function newAction()
	{
		// do exactly the same as in editAction, but with a different title
		$this->editAction();
		$this->_helper->viewRenderer->setScriptAction('edit');
	}

	/**
	 * Edits/creates a country
	 * @user-level manager
	 */
	public function editAction()
	{
		if ($this->_request->getActionName() == 'edit') {
			$editingCountry = $this->getRequestedCountry();
            $this->view->isNew = false;
		} else {
			$editingCountry = new Model_Country();
            $this->view->isNew = true;
		}

		$this->view->countryCodes = Model_Country::countryCodes();

		if ($this->_request->isPost()) {

			$requestParams = $this->_request->getParams();
			
			$isValidInput = $this->verifyInput([
				$requestParams['display_name'] => [
					'inputLabel' => $this->formInputLabels['display_name'],
					'validator' => new Validation\StringValidator(),
					'required' => true
				],
				$requestParams['penetration'] => [
					'inputLabel' => $this->formInputLabels['penetration'],
					'validator' => new Validation\RangeValidator(0, 100)
				],
				$requestParams['country'] => [
					'inputLabel' => $this->formInputLabels['country'],
					'validator' => new Validation\ListValidator(array_keys($this->view->countryCodes)),
					'required' => true
				]
			]);

			if ($isValidInput && $this->setProperties($editingCountry,  $requestParams)) {
				$editingCountry->penetration = max(0, $editingCountry->penetration);
				$editingCountry->penetration = min(100, $editingCountry->penetration);

				try {
					$editingCountry->save();

					$this->invalidateTableCache();

                    $this->flashMessage($this->translator->trans('route.country.edit.message.success'));
					$this->_helper->redirector->gotoRoute(array('action' => 'view', 'id' => $editingCountry->id));
				} catch (Exception $ex) {
					if (strpos($ex->getMessage(), '23000') !== false) {
                        $this->flashMessage($this->translator->trans('Error.display-name-exists'), 'error');
					} else {
                        $this->flashMessage($ex->getMessage(), 'error');
					}
				}
			}
		}

		$this->view->editingCountry = $editingCountry;
	}

    /**
     * Edits all countries
	 *
     * @user-level manager
     */
    public function editAllAction()
    {

        $this->view->countries = Model_Country::fetchAll();
        $this->view->countryCodes = Model_Country::countryCodes();

        if ($this->_request->isPost()) {

            $result = $this->_request->getParams();

            $editingCountries = array();

            foreach($result as $k => $v){
                if(preg_match('|^([0-9]+)\_(.+)$|', $k, $matches)){
                    if(!array_key_exists($matches[1], $editingCountries)) $editingCountries[$matches[1]] = array('id' => $matches[1]);
                    $editingCountries[$matches[1]][$matches[2]] = $v;
                }
            }

            $producedErrors = false;

            $editedCountries = array();

//			$oldTimeZone = $editingCountry->timezone;
            foreach($editingCountries as $c){
                $editingCountry = Model_Country::fetchById($c['id']);

				$isValidInput = $this->verifyInput([
					$c['display_name'] => [
						'inputLabel' => $this->formInputLabels['display_name'],
						'validator' => new Validation\StringValidator(),
						'required' => true
					],
					$c['penetration'] => [
						'inputLabel' => $this->formInputLabels['penetration'],
						'validator' => new Validation\RangeValidator(0, 100)
					],
					$c['country'] => [
						'inputLabel' => $this->formInputLabels['country'],
						'validator' => new Validation\ListValidator(array_keys($this->view->countryCodes)),
						'required' => true
					]
				]);

				if(!$isValidInput || !$this->setProperties($editingCountry, $c)){
					$producedErrors = true;
					break;
				};

                $editedCountries[] = $editingCountry;
            }

            if (!$producedErrors) {
                try {
					/** @var Model_Country $country */
					foreach($editedCountries as $country){
                        $country->save();
                    }

					$this->invalidateTableCache();

					$this->flashMessage($this->translator->trans('route.country.edit-all.message.success', ['%count%' => count($editedCountries)]));
                    $this->_helper->redirector->gotoSimple('index');

                } catch (Exception $ex) {
                    if (strpos($ex->getMessage(), '23000') !== false) {
                        $this->flashMessage($this->translator->trans('Error.display-name-exists'), 'error');
                    } else {
                        $this->flashMessage($ex->getMessage(), 'error');
                    }
                }
            }

        }
    }

	/**
	 * Manages the presences that belong to a country
	 * @user-level manager
	 */
	public function manageAction() {
		$country = $this->getRequestedCountry();

		if ($this->_request->isPost()) {
			$presenceIds = array();
			foreach ($this->_request->getParam('assigned') as $ids) {
				foreach ($ids as $id) {
					$presenceIds[] = $id;
				}
			}
			$country->assignPresences($presenceIds);

			$this->invalidateTableCache();

			$this->flashMessage($this->translator->trans('route.country.manage.message.success'));
			$this->_helper->redirector->gotoRoute(array('action'=>'view'));
		}

		$this->view->country = $country;
		$this->view->presences = $this->managePresencesList();
	}

	/**
	 * Deletes a country
	 * @user-level manager
	 */
	public function deleteAction() {
		$country = $this->getRequestedCountry();

		if ($this->_request->isPost()) {
			$country->delete();

			$this->invalidateTableCache();

            $this->flashMessage($this->translator->trans('route.country.delete.message.success'));
    		$this->_helper->redirector->gotoSimple('index');
        } else {
            $this->flashMessage($this->translator->trans('Error.invalid-delete'));
            $this->_helper->redirector->gotoRoute(array('action'=>'view'));
		}
	}

	/**
	 * Gets all of the graph data for the requested presence
	 * @user-level user
	 */
	public function graphDataAction() {
		Zend_Session::writeClose(); //release session on long running actions

		$this->validateChartRequest();

		$country = $this->getRequestedCountry(false);
		if(!$country) {
			$this->apiError($this->translator->trans('route.country.graph-data.message.not-found'));
		}

		$dateRange = $this->getRequestDateRange();
		$start = $dateRange[0];
		$end = $dateRange[1];

		$chartName = $this->_request->getParam('chart');
		$chartObject = $this->getContainer()->get('chart.' . $chartName);

		$this->apiSuccess($chartObject->getChart($country, $start, $end));
	}


	/**
	 * Downloads a csv of data from the country index page
	 * @user-level user
	 */
	public function downloadAction() {
		$table = $this->getContainer()->get('table.country-index');
        $csvData = Util_Csv::generateCsvData($table);
        Util_Csv::outputCsv($csvData, 'countries');
        exit;
	}

	function getIndexTable($objectCacheManager)
	{
		return $objectCacheManager->getCountriesTable();
	}

}
