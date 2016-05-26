<?php

require_once __DIR__.'/../bootstrap.php';


//set up logging
//$logger = new Zend_Log();
//if (APPLICATION_ENV != 'prod') {
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

	header("X-Frame-Options: SAMEORIGIN");
	$front->dispatch();
}

//simple debug function
// function dbg($message, $level = 'info') {
//	 $GLOBALS['logger']->$level($message);
// }