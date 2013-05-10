<?php

class BaseController extends Zend_Controller_Action {

	protected $publicActions = array();

	static $optionCache = array();

	/**
	 * @var Zend_Config
	 */
	protected $config;

	public function preDispatch()
	{
		$this->auth = Zend_Auth::getInstance();
		$this->view->user = null;

		//try to load the user
		if ($this->auth->hasIdentity()) {
			$this->view->user = Model_User::fetchById($this->auth->getIdentity());

			if ($this->view->user) {

                date_default_timezone_set('UTC');
                //get/set default date range
                if (!isset($_SESSION['dateRange'])) {
                    $today = date('Y-m-d');
                    $_SESSION['dateRange'] = array($today, date('Y-m-d', strtotime($today . ' +1 day')));
                }
                $this->view->jsonDateRange = json_encode($_SESSION['dateRange']);

                //set start date
                $this->view->dateRangeString = date($this->config->app->dateFormat, strtotime($_SESSION['dateRange'][0]));
                $interval = date_diff(date_create($_SESSION['dateRange'][0]), date_create($_SESSION['dateRange'][1]));
                //only show second date if range > 1 day
                if ($interval->days > 1) {
                    $this->view->dateRangeString .= ' - ' . date($this->config->app->dateFormat, strtotime($_SESSION['dateRange'][1] . ' -1 day'));
                }

			}

		}

		//if user hasn't been loaded and this is not a public action, go to login
		if (!$this->view->user && !in_array($this->_request->getActionName(), $this->publicActions)) {
			$this->auth->clearIdentity();
			if (PHP_SAPI == 'cli') {
				die ('Not authorised');
			} elseif ($this->_request->isXmlHttpRequest()) {
				$this->apiError('Not logged in');
			} else {
				$this->forward('login', 'user');
			}
		}

		$permission = $this->view->gatekeeper()->getRequiredPermission($this->_request->getControllerName(), $this->_request->getActionName());
		if ($permission) {
			$this->rejectIfNotAllowed($permission);
		}
	}

	public function init() {
//		if (APPLICATION_ENV == 'live') {
//			$this->getResponse()
//				->setHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
//		}
		$this->config = Zend_Registry::get('config');

		$this->view->bodyClass = $this->_request->getActionName().'Action';
		// provide a default page title
		$this->view->title = ucfirst($this->_request->getControllerName()) . ' > ' . ucfirst($this->_request->getActionName());

		//calculate twitter api status
		$this->view->apiStatus = array();

		//set up navigation
		$navConfig = new Zend_Config_Yaml(APPLICATION_PATH . '/configs/navigation.yaml');
		$navigation = new Zend_Navigation($navConfig);
		$this->setActivePages($navigation->getPages());
		$this->view->navigation($navigation);

		// set up admin navigation
		foreach (array('adminMenu'=>'navigation_admin.yaml', 'nonAdminMenu'=>'navigation_non_admin.yaml') as $property=>$configFile) {
			$navConfig = new Zend_Config_Yaml(APPLICATION_PATH . '/configs/' . $configFile);
			$navigation = new Zend_Navigation($navConfig);
			foreach ($navigation->getPages() as $page) {
				if (strpos($page->getClass(), 'notab') === false) {
					$page->setActive($page->getController() == $this->_request->getControllerName());
				}
			}
			$this->view->$property = $navigation;
		}

		//set JS config to be output in the page
		$configArray = $this->config->toArray();
		$this->view->jsConfig = $configArray['jsConfig'];
		$this->view->jsConfig['apiEndpoint'] = $this->view->baseUrl('/');

		// set up the metrics ranges and colours
		$colors = (object)array(
			'red' => '#FF0000',
			'green' => '#008000',
			'orange' => '#FFA500',
			'yellow' => '#FFFF00'
		);
		$metrics = array();
		foreach (Model_Campaign::getKpis() as $key=>$label) {
			$metrics[$key] = (object)array('label'=>$label);
		}
		$metrics[Model_Campaign::KPI_POPULARITY_PERCENTAGE]->range = array(0, 50, 100);
		$metrics[Model_Campaign::KPI_POPULARITY_PERCENTAGE]->colors = array($colors->red, $colors->yellow, $colors->green);

		$audienceBest = self::getOption('achieve_audience_best');
		$audienceGood = self::getOption('achieve_audience_good');
		$audienceBad = self::getOption('achieve_audience_bad');
		$metrics[Model_Campaign::KPI_POPULARITY_TIME]->range = array($audienceBest, $audienceGood, $audienceBad, $audienceGood+$audienceBad);
		$metrics[Model_Campaign::KPI_POPULARITY_TIME]->colors = array($colors->green, $colors->yellow, $colors->orange, $colors->red);

		$postsPerDay = self::getOption('updates_per_day');
		$postsPerDayOk = self::getOption('updates_per_day_ok_range');
		$postsPerDayBad = self::getOption('updates_per_day_bad_range');
		$metrics[Model_Campaign::KPI_POSTS_PER_DAY]->range = array(0, $postsPerDay - $postsPerDayBad, $postsPerDay - $postsPerDayOk, $postsPerDay + $postsPerDayOk, $postsPerDay + $postsPerDayBad, max($postsPerDay + $postsPerDayBad + 1, 2*$postsPerDay));
		$metrics[Model_Campaign::KPI_POSTS_PER_DAY]->colors = array($colors->red, $colors->yellow, $colors->green, $colors->green, $colors->yellow, $colors->red);
		// convert each hex string to rgb values
		foreach ($metrics as $args) {
			$args->colorsRgb = array();
			foreach ($args->colors as $color) {
				$rgb = array();
				for ($i=1; $i<6; $i+=2) {
					$rgb[] = hexdec(substr($color, $i, 2));
				}
				$args->colorsRgb[] = $rgb;
			}
		}
		$this->view->metrics = $metrics;
	}

	/**
	 * Recursively set each page's active state, depending on whether they or their children are currently being shown
	 * @param $pages
	 * @return bool true if an active page is found within $pages
	 */
	protected function setActivePages($pages) {
		$foundActive = false;
		foreach ($pages as $page) {
			if (strpos($page->getClass(), 'notab') === false) {
				$hasActiveChild = $this->setActivePages($page->getPages());
				$active = $hasActiveChild || $page->getController() == $this->_request->getControllerName();
				$page->setActive($active);
				$foundActive |= $active;
			}
		}
		return $foundActive;
	}
	/**
	 * shows a message and redirects to index page if current user has insufficient permissions to perform the given action
	 * @param $action
	 */
	protected function rejectIfNotAllowed($action) {
		//todo: reinstate this when all permissions have been ironed out
//		if (!$this->view->user->canPerform($action)) {
//			$message = 'Not allowed: Insufficient user privileges';
//			if (APPLICATION_ENV != 'live') {
//				$message .= ' (' . $action . ')';
//			}
//			if ($this->_request->isXmlHttpRequest()) {
//				$this->apiError($message);
//			} else {
//				$this->_helper->FlashMessenger(array('error' => $message));
//				$urlArgs = $this->view->gatekeeper()->fallbackUrlArgs(array('action'=>'index'));
//				$this->_helper->redirector->gotoRoute($urlArgs, null, true);
//			}
//		}
	}

	/**
	 * If the item is null, this will trigger a redirect (i.e. the action is exited) and an error message
	 * @param $item
	 * @param null $type
	 * @param null $action
	 */
	protected function validateData($item, $type = null, $action = null) {
		if (!$item) {
			$this->dataNotFound($type);
		}
	}
	
	// flashes a '[controller name] not found' message, then redirects to the index page
	protected function dataNotFound($type = null)
	{
		if (!$type) {
			$type = $this->_request->getControllerName();
		}
		$this->_helper->FlashMessenger(array('error' => $type . ' not found'));
		$this->_helper->redirector->gotoSimple('');
	}

	//format a json response with data
	protected function apiSuccess($data = null, $messages = array()) {
		$flashMessenger = Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger');

		//merge current and previous flash messages
		if (Zend_Session::isWritable()) {
			$messages = array_merge($messages, $flashMessenger->getMessages());
			if ($flashMessenger->hasCurrentMessages()) {
				$messages = array_merge(
					$messages,
					$flashMessenger->getCurrentMessages()
				);
				//don't show current on next request
				$flashMessenger->clearCurrentMessages();
			}
		}

		$ret = array(
			'status' => 'OK',
			'request' => array(
				'path' => $this->_request->getControllerName().'/'.$this->_request->getActionName(),
				'args' => $_REQUEST
			),
			'messages' => $messages,
			'data' => $data
		);

		if (!headers_sent()) {
			header('Content-type: application/json');
		}

		echo json_encode($ret);

		exit;
	}

	//format a json response with error message and send a 500 header
	protected function apiError($message) {
		$ret = array(
			'status' => 'error',
			'request' => array(
				'path' => $this->_request->getControllerName().'/'.$this->_request->getActionName(),
				'args' => $_REQUEST
			),
			'error' => $message
		);
		$this->_response->setHttpResponseCode(500);
		$this->_helper->json($ret);
	}

	protected function returnCsv($data, $filename = null) {

		$this->_response->setHeader('Content-type', 'application/octet-stream');
		$this->_response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');


		//make header
		if ($data) {
			$headers = array_keys($data[0]);
			$headers = array_map(function($name) {
				return ucfirst(str_replace('_', ' ', $name));
			}, $headers);
			echo implode(',', $headers) . "\n";
		}

		foreach ($data as $row) {
			//quote data
			$quotedRow = array();
			foreach ($row as $cell) {
				$quotedRow[] = '"' . str_replace('"', '""', $cell) . '"';
			}
			//output immediately (to save memory)
			echo implode(',', $quotedRow) . "\n";
		}

		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
	}
	
	protected function getRequestDateRange() {
		if (empty($this->_request->dateRange)) { 
			$dateRange = null;
		} else {
			$dateRange = $this->_request->dateRange;
			if (!is_array($dateRange)) {
				$dateRange = explode(',', $dateRange);
			}
			foreach ($dateRange as $i => $date) {
				if (!$date) {
					$dateRange = null;
					break;
				} else {
					$dateRange[$i];
				}
			}
		}
		return $dateRange;
	}
	
	// creates an array of ordering arguments (propName=>direction) from the datatables request args
	protected function getRequestOrdering() {
		$ordering = array();
		for ($i=0; $i<$this->_request->iSortingCols; $i++) {
			$propIndex = $this->_request->{"iSortCol_$i"};
			$propName = $this->_request->{"mDataProp_$propIndex"};
			$ordering[$propName] = $this->_request->{"sSortDir_$i"};
		}
		return $ordering;
	}
	
	protected function getRequestSearchQuery() {
		return $this->_request->sSearch;
	}
	
	protected function getRequestLimit() {
		return $this->_request->iDisplayLength ? $this->_request->iDisplayLength : -1;
	}
	
	protected function getRequestOffset() {
		return $this->_request->iDisplayStart ? $this->_request->iDisplayStart : -1;
	}
	
	protected function getRequestTopics() {
		if ($this->_request->topics) {
			return explode(',', $this->_request->topics);
		} else {
			return array();
		}
	}

	public static function makeLineId($className, $modelId, $filterType = '', $filterValue = '') {
		return implode(':', array(substr($className, 6), $modelId, $filterType, $filterValue));
	}

	public static function parseLineId($lineId) {
		$bits = explode(':', $lineId, 4);
		return array(
			'modelType' => $bits[0],
			'modelClass' => 'Model_'.$bits[0],
			'modelId' => $bits[1],
			'filterType' => count($bits) > 2 ? $bits[2] : '',
			'filterValue' => count($bits) > 3 ? $bits[3] : ''
		);
	}

	/**
	 * @return PDO
	 */
	public static function db() {
		return Zend_Registry::get('db');
	}

	/**
	 * Fetch back an option
	 * @static
	 * @param $name string Option name
	 * @return string|bool The stored value or false if no value exists
	 */
	public static function getOption($name) {
		if (array_key_exists($name, BaseController::$optionCache)) {
			return BaseController::$optionCache[$name];
		}
		$sql = 'SELECT value FROM options WHERE name = :name LIMIT 1';
		$statement = self::db()->prepare($sql);
		$statement->execute(array(':name' => $name));
		$value = $statement->fetchColumn();
		BaseController::$optionCache[$name] = $value;
		return $value;
	}

	/**
	 * Store some arbitrary value in the options table
	 * @static
	 * @param $name string Option name
	 * @param $value string Option value
	 * @return void
	 */
	public static function setOption($name, $value) {
		$statement = self::db()->prepare('REPLACE INTO options (name, value) VALUES (:name, :value)');
		$statement->execute(array(':name' => $name, ':value' => $value));
		if (array_key_exists($name, BaseController::$optionCache)) {
			unset(BaseController::$optionCache[$name]);
		}
	}

	/**
	 * Sends an email with the given properties
	 * @param $message
	 * @param $fromEmail
	 * @param $fromName
	 * @param $toEmail
	 * @param $subject
	 */
	protected function sendEmail($message, $fromEmail, $fromName, $toEmail, $subject) {
		//if on windows, send using gmail
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$config = array(
				'ssl' => 'tls',
				'port' => 587,
				'auth' => 'login',
				'username' => 'tamlynrhodes',
				'password' => 'ztkvjliiueqbtdlf'
			);
			$transport = new Zend_Mail_Transport_Smtp('smtp.gmail.com', $config);
			Zend_Mail::setDefaultTransport($transport);
		}

		$mail = new Zend_Mail();
		if (preg_match('/<.+?>/', $message)) {
			$mail->setBodyHtml($message);
		} else {
			$mail->setBodyText($message);
		}
		$mail->setFrom($fromEmail, $fromName);
		$mail->addTo($toEmail);
		$mail->setSubject($subject);
		$mail->send();
	}
}