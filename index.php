<?php

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', __DIR__ . '/application');

defined('APP_ROOT_PATH')
    || define('APP_ROOT_PATH', __DIR__);

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?: 'live'));


//error reporting
if (APPLICATION_ENV != 'live') {
	error_reporting(E_ALL);
	ini_set('display_errors', true);
}

//autoloader to load zend components on demand
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();

Zend_Session::start();

if (file_exists(APP_ROOT_PATH.'/maintenance.html')) {
	if (isset($_REQUEST['bypass']) || isset($_SESSION['bypass']) || PHP_SAPI == 'cli') {
		$_SESSION['bypass'] = true;
	} else {
		include APP_ROOT_PATH.'/maintenance.html';
		exit;
	}
}


require_once APPLICATION_PATH.'/controllers/BaseController.php';
require_once APPLICATION_PATH.'/controllers/GraphingController.php';
require_once APPLICATION_PATH.'/controllers/CampaignController.php';

$resourceLoader = new Zend_Application_Module_Autoloader(array(
	'namespace' => '',
	'basePath'  => APPLICATION_PATH
));
$resourceLoader->addResourceType('exception', 'exceptions/', 'Exception');
$resourceLoader->addResourceType('util', 'util/', 'Util');
$resourceLoader->addResourceType('metric', 'metrics/', 'Metric');
$resourceLoader->addResourceType('badge', 'badges/', 'Badge');
$resourceLoader->addResourceType('chart', 'charts/', 'Chart');
$resourceLoader->addResourceType('header', 'table_headers/', 'Header');
$resourceLoader->addResourceType('provider', 'providers/', 'Provider');
$resourceLoader->addResourceType('enum', 'enum/', 'Enum');

//load config
if (!file_exists(APPLICATION_PATH . '/configs/config.yaml')) {
	die('Please copy configs/config.sample.yaml to configs/config.yaml');
}
$config = new Zend_Config_Yaml(
    APPLICATION_PATH . '/configs/config.yaml',
    APPLICATION_ENV
);
Zend_Registry::set('config', $config);

//connect to database
$db = Zend_Db::factory($config->db);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Registry::set('db', $db);


//set db for PresenceFactory
Model_PresenceFactory::setDatabase($db->getConnection());

//set up logging
//$logger = new Zend_Log();
//if (APPLICATION_ENV != 'live') {
//	$writer = new Zend_Log_Writer_Firebug();
//} else {
//	$writer = new Zend_Log_Writer_Stream('php://stderr');
//}
//$logger->addWriter($writer);

//set up front controller
$front = Zend_Controller_Front::getInstance();
$front->setControllerDirectory(array(
	'default' => APPLICATION_PATH.'/controllers'
));
// $front->throwExceptions(true);
// $front->setParams(array(
	// 'noErrorHandler' => true,
	// 'db' => $db,
	// 'logger' => $logger
// ));
$front->registerPlugin(new Zend_Controller_Plugin_ErrorHandler());

// Set up BundlePhu which combines and compresses JS and CSS
$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
$viewRenderer->initView();
$viewRenderer->view->addHelperPath(APP_ROOT_PATH . '/lib/BundlePhu/View/Helper', 'BundlePhu_View_Helper');

Zend_Layout::startMvc();

//check if we're running on the command line
//  example:
//    php index.php fetch analyse silent=1
if (PHP_SAPI == 'cli') {

	echo 'env: ' . APPLICATION_ENV . PHP_EOL;

	//first two arguments are controller and action to run
	$controller = isset($argv[1]) ? $argv[1] : 'index';
	$action = isset($argv[2]) ? $argv[2] : 'index';

	//further arguments take form name1=value1 name2 name3=value3
	$extraArgs = array();
	if ($argc > 3) {
		for ($i = 3; $i < $argc; $i++) {
			if (strpos($argv[$i], '=')) {
				list($name, $value) = explode('=', $argv[$i]);
			} else {
				$name = $argv[$i];
				$value = true;
			}
			$extraArgs[$name] = $value;
		}
	}

	$request = new Zend_Controller_Request_Simple($action, $controller, null, $extraArgs);
	$response = new Zend_Controller_Response_Cli();

	$dispatcher = $front->getDispatcher();
	$dispatcher->dispatch($request, $response);

	echo $response->getBody();

} else {

	//add layout to view
	Zend_Layout::getMvcInstance()->setLayout('layout');

	$front->dispatch();
}

//simple debug function
// function dbg($message, $level = 'info') {
//	 $GLOBALS['logger']->$level($message);
// }