<?php

require_once 'vendor/autoload.php';

class SinaWeiboProviderTest extends PHPUnit_Extensions_Database_TestCase
{
	// only instantiate pdo once for test clean-up/fixture load
    static private $pdo = null;

    // only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
    private $conn = null;

    private $provider;

    final public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PDO( $GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD'] );
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS['DB_DBNAME']);
        }

        return $this->conn;
    }

    public function getDataSet()
    {
    	return $this->createFlatXmlDataSet('tests/sina-weibo-dataset.xml');
    }

    public function setUp()
    {
    	defined('APPLICATION_PATH')
    		|| define('APPLICATION_PATH', __DIR__ . '/../application');
    	require_once 'Zend/Loader/Autoloader.php';
		$autoloader = Zend_Loader_Autoloader::getInstance();
		$resourceLoader = new Zend_Application_Module_Autoloader(array(
			'namespace' => '',
			'basePath'  => APPLICATION_PATH
		));
		$resourceLoader->addResourceType('exception', 'exceptions/', 'Exception');
		$resourceLoader->addResourceType('util', 'util/', 'Util');
		$resourceLoader->addResourceType('refactored', 'models/refactored/', 'Model');
		$this->getConnection();
    	$this->provider = new Model_SinaWeiboProvider(self::$pdo);
    }
}