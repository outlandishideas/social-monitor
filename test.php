<?php
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', __DIR__ . '/application');
require_once 'Zend/Loader/Autoloader.php';
$autoloader = Zend_Loader_Autoloader::getInstance();
$resourceLoader = new Zend_Application_Module_Autoloader(array(
	'namespace' => '',
	'basePath'  => APPLICATION_PATH
));
$resourceLoader->addResourceType('exception', 'exceptions/', 'Exception');
$resourceLoader->addResourceType('util', 'util/', 'Util');
$resourceLoader->addResourceType('refactored', 'models/refactored/', 'NewModel');
$resourceLoader->addResourceType('metric', 'metrics/', 'Metric');
$resourceLoader->addResourceType('badge', 'badges/', 'Badge');


// $pdo = new PDO('mysql:dbname=bcmonitor_test;host=localhost', 'bcmonitor_test', 'passwd');
// NewModel_PresenceFactory::setDatabase($pdo);
// Badge_Factory::setDB($pdo);
// $presence = NewModel_PresenceFactory::getPresenceByHandle('learnenglish', Enum_PresenceType::SINA_WEIBO());

// $provider = new NewModel_SinaWeiboProvider($pdo);

// //$provider->fetchStatusData($presence);

// $data = $provider->getHistoricStream($presence, new DateTime('@'.strtotime('-1 month')), new DateTime('@'.time()));
// var_dump($data);

// $badges = $presence->getBadges();
// foreach ($badges as $b) {
// 	$b->calculate($presence);
// }
//

require_once('lib/sina_weibo/sinaweibo.php');

$conn = new SaeTClientV2('1247980630', 'cfcd7c7170b70420d7e1c00628d639c2', '2.00cBChiFjjAm3C216d675d51UwGFRE');

var_dump($conn->rate_limit_status());