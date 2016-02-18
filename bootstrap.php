<?php

// Define path to application directory
defined('APPLICATION_PATH')
|| define('APPLICATION_PATH', __DIR__ . '/application');

defined('APP_ROOT_PATH')
|| define('APP_ROOT_PATH', __DIR__);

// Define application environment
defined('APPLICATION_ENV')
|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?: 'alpha'));


//error reporting
if (APPLICATION_ENV != 'live') {
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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__));
$loader->load('parameters.yml');
$loader->load('services.yml');

Enum_PresenceType::setContainer($container);
BaseController::setContainer($container);


//set db for PresenceFactory
Model_PresenceFactory::setDatabase($db->getConnection());
