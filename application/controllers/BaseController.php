<?php

class BaseController extends Zend_Controller_Action
{

    protected static $publicActions = array();

    static $optionCache = array();

    /** @var Zend_Config */
    protected $config;
    /** @var Zend_Auth */
    protected $auth;

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
                    $_SESSION['dateRange'] = array(date('Y-m-d', strtotime($today . ' -30 days')), date('Y-m-d', strtotime($today . ' +1 day')));
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

	    // check the fetch lock
	    $lockTime = $this->getOption($this->lockName('fetch'));
	    if ($lockTime && $this->_request instanceof Zend_Controller_Request_Http && !$this->_request->isXmlHttpRequest()) {
		    $seconds = time() - $lockTime;
		    if ($seconds > (10 * $this->config->app->fetch_time_limit)) {
			    $factors = array(
				    'day' => 86400,
				    'hour' => 3600,
				    'min' => 60,
				    'sec' => 0
			    );
			    $elements = array();
			    foreach ($factors as $label => $factor) {
				    if ($seconds > $factor) {
					    if ($factor) {
						    $tmp = $seconds % $factor;
						    $elements[] = array(($seconds - $tmp) / $factor, $label);
						    $seconds = $tmp;
					    } else {
						    $elements[] = array($seconds, $label);
					    }
				    }
			    }
			    foreach ($elements as $i => $e) {
				    $elements[$i] = $this->view->pluralise($e[1], $e[0]);
			    }
			    $message = 'Fetch process has been inactive for ' . implode(', ', $elements) . ', indicating something has gone wrong. ';
			    $urlArgs = array('controller'=>'fetch', 'action'=>'clear-lock');
			    $url = $this->view->gatekeeper()->filter('%url%', $urlArgs);
			    if ($url) {
				    $message .= 'Click <a href="' . $url . '">here</a> to clear the lock manually.';
			    } else {
				    $message .= 'Please log in to clear the lock.';
			    }
			    $this->flashMessage($message, 'inaction');
		    }
	    }

	    //if user hasn't been loaded and this is not a public action, go to login
        if (!$this->view->user && !in_array($this->_request->getActionName(), static::$publicActions)) {
            $this->auth->clearIdentity();
            if (PHP_SAPI == 'cli') {
                die ('Not authorised');
            } elseif ($this->_request->isXmlHttpRequest()) {
                $this->apiError('Not logged in');
            } else {
                $this->forward('login', 'user');
            }
        }

        $controller = $this->_request->getControllerName();
        $action = $this->_request->getActionName();
        $id = $this->_request->getParam('id');
        $this->rejectIfNotAllowed($controller, $action, $id);
    }

    public function init()
    {
//		if (APPLICATION_ENV == 'live') {
//			$this->getResponse()
//				->setHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
//		}
        $this->config = Zend_Registry::get('config');

        $this->view->bodyClass = $this->_request->getActionName() . 'Action '.$this->_request->getControllerName().'Controller';
        // provide a default page title
        $this->view->title = ucfirst($this->_request->getControllerName()) . ' > ' . ucfirst($this->_request->getActionName());
        $this->view->subtitle = '';
        $this->view->titleImage = '';
        $this->view->titleIcon = '';

        //calculate twitter api status
        $this->view->apiStatus = array();

        //set up navigation
        $navConfig = new Zend_Config_Yaml(APPLICATION_PATH . '/configs/navigation.yaml');
        $navigation = new Zend_Navigation($navConfig);
        $this->setActivePages($navigation->getPages());
        $this->view->navigation($navigation);

        // set up admin navigation
        foreach (array('adminMenu' => 'navigation_admin.yaml', 'nonAdminMenu' => 'navigation_non_admin.yaml', 'nonUserMenu' => 'navigation_non_user.yaml') as $property => $configFile) {
            $navConfig = new Zend_Config_Yaml(APPLICATION_PATH . '/configs/' . $configFile);
            $navigation = new Zend_Navigation($navConfig);
            /** @var Zend_Navigation_Page_Mvc $page */
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
    }

    /**
     * Recursively set each page's active state, depending on whether they or their children are currently being shown
     * @param $pages Zend_Navigation_Page_Mvc[]
     * @return bool true if an active page is found within $pages
     */
    protected function setActivePages($pages)
    {
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
     * shows a message and redirects to index page if current user has insufficient access rights
     * @param $controller
     * @param $action
     * @param $id
     * @internal param $level
     * @internal param $action
     */
    protected function rejectIfNotAllowed($controller, $action, $id)
    {
        $level = $this->view->gatekeeper()->getRequiredUserLevel($controller, $action);
        if ($this->view->user && !$this->view->user->canPerform($level, $controller, $id)) {
            $message = 'Not allowed: Insufficient access rights';
            if (APPLICATION_ENV != 'live') {
                $message .= ' (' . implode('/', array_filter(array($controller, $action, $id))) . ')';
            }
            if ($this->_request->isXmlHttpRequest()) {
                $this->apiError($message);
            } else {
                $this->flashMessage($message, 'error');
                $urlArgs = $this->view->gatekeeper()->fallbackUrlArgs(array('action' => 'index'));
                $this->_helper->redirector->gotoRoute($urlArgs, null, true);
            }
        }
    }

    /**
     * If the item is null, this will trigger a redirect (i.e. the action is exited) and an error message
     * @param $item
     * @param null $type
     * @param null $action
     */
    protected function validateData($item, $type = null, $action = null)
    {
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
        $this->flashMessage($type . ' not found', 'error');
        $this->_helper->redirector->gotoSimple('');
    }

    //format a json response with data
    protected function apiSuccess($data = null, $messages = array())
    {
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
                'path' => $this->_request->getControllerName() . '/' . $this->_request->getActionName(),
                'args' => $_REQUEST
            ),
            'messages' => $messages,
            'data' => $data
        );

        if (!headers_sent()) {
            header('Content-type: application/json');
        }

        $json = json_encode($ret);
        echo $json;

        exit;
    }

    //format a json response with error message and send a 500 header
    protected function apiError($message)
    {
        $ret = array(
            'status' => 'error',
            'request' => array(
                'path' => $this->_request->getControllerName() . '/' . $this->_request->getActionName(),
                'args' => $_REQUEST
            ),
            'error' => $message
        );
        $this->_response->setHttpResponseCode(500);
        $this->_helper->json($ret);
    }

    protected function returnCsv($data, $filename = null)
    {

        $this->_response->setHeader('Content-type', 'application/octet-stream');
        $this->_response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');


        //make header
        if ($data) {
            $headers = array_keys($data[0]);
            $headers = array_map(function ($name) {
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

    protected function getRequestDateRange()
    {
        $dateRange = $this->_request->getParam('dateRange');
        if ($dateRange) {
            if (!is_array($dateRange)) {
                $dateRange = explode(',', $dateRange);
            }
            foreach ($dateRange as $i => $date) {
                if (!$date) {
                    $dateRange = null;
                    break;
                } else {
                    try {
                        $dateRange[$i] = new DateTime($date);
                    } catch (Exception $e) {
                        $dateRange = null;
                    }
                }
            }
        }
        return $dateRange;
    }

    // creates an array of ordering arguments (propName=>direction) from the datatables request args
    protected function getRequestOrdering()
    {
        $ordering = array();
        $cols = $this->_request->getParam('iSortingCols');
        for ($i = 0; $i < $cols; $i++) {
            $propIndex = $this->_request->getParam("iSortCol_$i");
            $propName = $this->_request->getParam("mDataProp_$propIndex");
            $ordering[$propName] = $this->_request->getParam("sSortDir_$i");
        }
        return $ordering;
    }

    protected function getRequestSearchQuery()
    {
        return $this->_request->getParam('sSearch');
    }

    protected function getRequestLimit()
    {
        return $this->_request->getParam('iDisplayLength', -1);
    }

    protected function getRequestOffset()
    {
        return $this->_request->getParam('iDisplayStart', -1);
    }

    public static function parseLineId($lineId)
    {
        $bits = explode(':', $lineId, 4);
        return array(
            'modelType' => $bits[0],
            'modelClass' => 'Model_' . $bits[0],
            'modelId' => $bits[1],
            'filterType' => count($bits) > 2 ? $bits[2] : '',
            'filterValue' => count($bits) > 3 ? $bits[3] : ''
        );
    }

    /**
     * @return Zend_Db_Adapter_Pdo_Abstract
     */
    public static function db()
    {
        return Zend_Registry::get('db');
    }

    /**
     * Fetch back an option
     * @static
     * @param $name string Option name
     * @return string|bool The stored value or false if no value exists
     */
    public static function getOption($name)
    {
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
    public static function setOption($name, $value)
    {
        self::setOptions(array($name=>$value));
    }

    public static function setOptions($options) {
        $options = (array)$options;
        $statement = self::db()->prepare('REPLACE INTO options (name, value) VALUES (:name, :value)');
        foreach ($options as $name=>$value) {
            $statement->execute(array(':name' => $name, ':value' => $value));
            if (array_key_exists($name, BaseController::$optionCache)) {
                unset(BaseController::$optionCache[$name]);
            }
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
    protected function sendEmail($message, $fromEmail, $fromName, $toEmail, $subject)
    {
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

    protected function lockName($name = null)
    {
        $name = $name ? : $this->_request->getActionName();
        return $name . '_lock';
    }

    protected function logFileName($name = null)
    {
        $name = $name ? : $this->_request->getActionName();
        return APP_ROOT_PATH . '/log/' . $name . '.log';
    }

    public static function isPublicAction($action)
    {
        return in_array($action, static::$publicActions);
    }

    public static function setObjectCache($key, $value, $temp = false)
    {
		// delete any old/temporary entries for this key
	    $deleteSql = 'DELETE FROM object_cache WHERE `key` = :key';
	    $deleteArgs = array(':key' => $key);
	    if ($temp) {
		    $deleteSql .= ' AND `temporary` = :temp';
		    $deleteArgs[':temp'] = 1;
	    }
        $delete = self::db()->prepare($deleteSql);
        $delete->execute($deleteArgs);

	    $insert = self::db()->prepare('INSERT INTO object_cache (`key`, value, `temporary`) VALUES (:key, :value, :temp)');
        $insert->execute(array(':key' => $key, ':value' => gzcompress(json_encode($value)), ':temp' => $temp ? 1 : 0));
    }

    public static function getObjectCache($key, $allowTemp = true, $expires = 86400)
    {
        $sql = 'SELECT * FROM object_cache WHERE `key` = :key ORDER BY last_modified DESC LIMIT 1';
        $statement = self::db()->prepare($sql);
        $statement->execute(array(':key' => $key));
        $result = $statement->fetch(PDO::FETCH_OBJ);
	    if ($result) {
	        if ((time() - strtotime($result->last_modified)) < $expires && ( $allowTemp || $result->temporary == 0)) {
	            return json_decode(gzuncompress( $result->value));
	        }
	    }
        return false;
    }

    protected function flashMessage($message, $type = 'info') {
        $this->_helper->FlashMessenger(array($type => $message));
    }

    protected function setupConsoleOutput() {

        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout()->disableLayout();

        if (PHP_SAPI != 'cli'){
            header('Content-Type: text/plain');

            //output 1k of data to trigger rendering on client side, unless using CLI
            if (!$this->_request->getParam('silent')) {
                echo str_repeat(" ", 1024);
            }
        }

        //disable output buffering
        for ($i = 0; $i <= ob_get_level(); $i++) {
            ob_end_flush();
        }
        ob_implicit_flush(true);
    }

}