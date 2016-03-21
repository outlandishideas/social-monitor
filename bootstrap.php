<?php

// get OUTLANDISH_ENV from file in /var/projects/lib/include/env.php, if present
@include('env.php');

// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', __DIR__ . '/application');

defined('APP_ROOT_PATH')
|| define('APP_ROOT_PATH', __DIR__);

// Define application environment
defined('APPLICATION_ENV')
|| define('APPLICATION_ENV', defined('OUTLANDISH_ENV') ? OUTLANDISH_ENV : (getenv('APPLICATION_ENV') ?: 'alpha'));


//error reporting
if (APPLICATION_ENV != 'prod') {
    error_reporting(E_ALL);
    ini_set('display_errors', true);
}


//autoloader to load zend components on demand
require __DIR__.'/vendor/autoload.php';
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
$resourceLoader->addResourceType('provider', 'providers/', 'Provider');
$resourceLoader->addResourceType('enum', 'enum/', 'Enum');


//initialise zend

if (!file_exists(APPLICATION_PATH . '/configs/config.yaml')) {
    die('Please copy ' . APPLICATION_PATH . '/configs/config.sample.yaml to ' . APPLICATION_PATH . '/configs/config.yaml');
}
$config = new Zend_Config_Yaml(
    APPLICATION_PATH . '/configs/config.yaml',
    APPLICATION_ENV
);
Zend_Registry::set('config', $config);

$db = Zend_Db::factory($config->db);
Zend_Db_Table::setDefaultAdapter($db);
Zend_Registry::set('db', $db);



// initialise Symfony

if (!file_exists(APP_ROOT_PATH . '/parameters.yml')) {
	die('Please copy ' . APP_ROOT_PATH . '/parameters.yml.dist to ' . APP_ROOT_PATH . '/parameters.yml, and populate it');
}

$translate = new Zend_Translate(
    array(
        'adapter' => 'csv',
        'content' => 'languages/lang.en',
        'locale'  => 'en'
    )
);
Zend_Registry::set('translate', $translate);

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__));
$loader->load('parameters.yml');
$loader->load('services.yml');


// give Zend components access to symfony

Enum_PresenceType::setContainer($container);
BaseController::setContainer($container);


//set db for PresenceFactory
Model_PresenceFactory::setDatabase($db->getConnection());
