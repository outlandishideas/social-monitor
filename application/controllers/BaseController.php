<?php

use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\Helper\Gatekeeper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Outlandish\SocialMonitor\Exception\InvalidPropertiesException;
use Outlandish\SocialMonitor\Exception\InvalidPropertyException;

class BaseController extends Zend_Controller_Action
{

    protected $formInputLabels = array();

    protected static $publicActions = array();

    static $optionCache = array();

    /** @var Zend_Config */
    protected $config;
    /** @var Zend_Auth */
    protected $auth;
    /**
     * @var ContainerInterface
     */
    static protected $container;
	/** @var \Symfony\Component\Translation\Translator */
	protected $translator;

	/**
     * @param ContainerInterface $container
     */
    public static function setContainer(ContainerInterface $container)
    {
        self::$container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return self::$container;
    }

    public function getCompanyName(){
        return $this->config->app->client_name;
    }

    public function preDispatch()
    {
        $this->auth = Zend_Auth::getInstance();
        $this->view->user = null;
		$this->view->clientName = $this->config->app->client_name;
		$this->view->locale = $this->getContainer()->getParameter('app.locale');

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

	    // check the fetch lock and show if has been locked over a period of time
        $this->showFetchLockMessage();

        $this->showAccessTokenNeedsRefreshMessage();

	    //if user hasn't been loaded and this is not a public action, go to login
        if (!$this->view->user && !in_array($this->_request->getActionName(), static::$publicActions)) {
            $this->auth->clearIdentity();
            if (PHP_SAPI == 'cli') {
                die ($this->translator->trans('Error.not-authorized')); //'Not authorised');
            } elseif ($this->_request->isXmlHttpRequest()) {
                $this->apiError($this->translator->trans('Error.not-logged-in'));
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
        $this->config = Zend_Registry::get('config');

		$translator = $this->getContainer()->get('translation.translator');

        $this->view->bodyClass = $this->_request->getActionName() . 'Action '.$this->_request->getControllerName().'Controller';
        // provide a default page title
		$controllerName = $this->_request->getControllerName();
		$actionName = $this->_request->getActionName();
		$pageTitleKey = 'route.' . $controllerName . '.' . $actionName . '.page-title';
		$pageTitle = $translator->trans($pageTitleKey);
		if ($pageTitle && $pageTitle != $pageTitleKey) {
			$this->view->pageTitle = $pageTitle;
		} else {
			$this->view->pageTitle = ucfirst($controllerName) . ' > ' . ucfirst($actionName);
		}
        $this->view->subtitle = '';
        $this->view->titleImage = '';

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
        $this->view->jsConfig['companyName'] = $this->getCompanyName();
        $this->view->jsConfig['dateLocale'] = $this->getContainer()->getParameter('date.locale');

		$this->translator = $translator;
    }

	/**
	 * Fills in any placeholders in the page title
	 * @param array $params
	 */
	protected function updatePageTitle($params)
	{
		$newParams = array();
		foreach ($params as $key=>$value) {
			if ($key[0] != '%') {
				$key = '%' . $key;
			}
			if ($key[strlen($key)-1] != '%') {
				$key .= '%';
			}
			$newParams[$key] = $value;
		}
		$this->view->pageTitle = strtr($this->view->pageTitle, $newParams);
	}

    /**
     * Recursively set each page's active state, depending on whether they or their children are currently being shown
     * @param $pages Zend_Navigation_Page[]
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
        /** @var Gatekeeper $gatekeeper */
        $gatekeeper = $this->view->gatekeeper();
        $level = $gatekeeper->getRequiredUserLevel($controller, $action);
        if ($this->view->user && !$this->view->user->canPerform($level, $controller, $action, $id)) {
            $message = $this->translator->trans('Error.not-allowed');
            if (APPLICATION_ENV != 'prod') {
                $message .= ' (' . implode('/', array_filter(array($controller, $action, $id))) . ')';
            }
            if ($this->_request->isXmlHttpRequest()) {
                $this->apiError($message);
            } else {
                $this->flashMessage($message, 'error');
                $urlArgs = $gatekeeper->fallbackUrlArgs(array('action' => 'index'));
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
        $this->flashMessage($this->translator->trans('Error.data-not-found', ['%type%' => $type]), 'error');
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
     * @return Database
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
     * @param $plainText - set this to true if the message is plain text
     * @return int - number of recipients message delivered to
     */
    protected function sendEmail($message, $fromEmail, $fromName, $toEmail, $subject, $plainText = false)
    {
        $contentType = $plainText ? 'text/plain' : 'text/html';

        $message = Swift_Message::newInstance($subject, $message, $contentType);
        $message->setFrom([$fromEmail => $fromName]);
        $message->setTo([$toEmail]);

        $transport = Swift_MailTransport::newInstance();
        $mailer = Swift_Mailer::newInstance($transport);
        return $mailer->send($message);
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

    /**
     * TODO: Remove this when Badge_Factory is moved (to a service?)
     * @param $key
     * @param $value
     * @param bool|false $temp
     */
    public static function setObjectCache($key, $value, $temp = false)
    {
        $cacheManager = self::$container->get('object-cache-manager');
        $cacheManager->setObjectCache($key, $value, $temp);
    }

    /**
     * TODO: Remove this when Badge_Factory is moved (to a service?)
     * @param $key
     * @param bool|true $allowTemp
     * @param int $expires
     * @return bool|mixed
     */
    public static function getObjectCache($key, $allowTemp = true, $expires = 86400)
    {
        $cacheManager = self::$container->get('object-cache-manager');
        return $cacheManager->getObjectCache($key, $allowTemp, $expires);
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

    /**
     * Updates the presence index cache
     *
     * The presence index table is very large and was performing very badly. This method calculates the needed
     * values for this table and stores it as json in the object cache table to be used when rendering that
     * particular page.
     */
    protected function updatePresenceIndexCache()
    {
        $presenceIndexTable = $this->getContainer()->get('table.presence-index');
        $presences = Model_PresenceFactory::getPresences();
        $rows = $presenceIndexTable->getRows($presences);
        BaseController::setObjectCache('presence-index', $rows);
    }

    protected function showFetchLockMessage()
    {
        $lockTime = $this->getOption($this->lockName('fetch'));
        if ($lockTime && $this->isHttpRequest()) {
            $seconds = time() - $lockTime;
            if ($this->fetchHasBeenLockedForTooLong($seconds)) {
                $message = $this->createFetchLockWarningMessage($seconds);
                $this->flashMessage($message, 'inaction');
            }
        }
    }

    /**
     * @return bool
     */
    protected function isHttpRequest()
    {
        return $this->_request instanceof Zend_Controller_Request_Http && !$this->_request->isXmlHttpRequest();
    }

    /**
     * @param $seconds
     * @return bool
     */
    protected function fetchHasBeenLockedForTooLong($seconds)
    {
        return $seconds > (10 * $this->config->app->fetch_time_limit);
    }

    /**
     * @param $seconds
     * @return string
     */
    protected function createFetchLockWarningMessage($seconds)
    {
        $elements = $this->secondsIntoFriendlyTime($seconds);
        $message = $this->translator->trans('Error.lock-error.message', ['%time%' => implode(', ', $elements)]);
        $urlArgs = array('controller' => 'fetch', 'action' => 'clear-lock');
        $url = $this->view->gatekeeper()->filter('%url%', $urlArgs);
        if ($url) {
            $message .= ' ' . $this->translator->trans('Error.lock-error.with-url', ['%url%' => $url]);
        } else {
            $message .= ' ' . $this->translator->trans('Error.lock-error.without-url');
        }
		return $message;
    }

    /**
     * @param $seconds
     * @return array
     */
    protected function secondsIntoFriendlyTime($seconds)
    {
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
        foreach ($elements as $index => $element) {
			$key = $element[1];
			$amount = $element[0];
            $elements[$index] = $this->translator->transChoice('route.base.fetch-lock.' . $key, $amount, ['%amount%' => $amount]);
        }
        return $elements;
    }

    protected function getInputLabel($property){
        if(array_key_exists($property, $this->formInputLabels)) {
            return $this->formInputLabels[$property];
        }else{
            return false;
        }
    }

    protected function setProperties($model, $properties){
        try {
            $model->fromArray($properties);
        }catch (InvalidPropertiesException $ex){
            $errorMessages = $ex->getProperties();
            foreach ($errorMessages as $invalidProperty) {
                $property = $invalidProperty->getProperty();
                $inputLabelKey = $this->getInputLabel($property);
                if($inputLabelKey){
                    $inputLabel = $this->translator->trans($inputLabelKey);
                    $this->flashMessage(join(" ", [$inputLabel, $invalidProperty->getMessage()]), 'error');
                }
            }
            return false;
        }
            return true;
    }

    private function showAccessTokenNeedsRefreshMessage()
    {
        /** @var Model_User $user */
        $user = $this->view->user;
        if ($user && $user->hasAccessTokensNeedRefreshing()) {
            $urlArgs = ['controller' => 'user', 'action' => 'edit-self'];
            $url = $this->view->gatekeeper()->filter('%url%', $urlArgs);
            $message = $this->translator->trans('Error.access-token.refresh', ['%url%' => $url]);
            $this->flashMessage($message, 'inaction');
        }
    }

}