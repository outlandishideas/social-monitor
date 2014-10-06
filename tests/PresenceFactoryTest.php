<?php

require_once 'vendor/autoload.php';

class PresenceFactoryTest extends PHPUnit_Extensions_Database_TestCase
{
	// only instantiate pdo once for test clean-up/fixture load
    static private $pdo = null;

    // only instantiate PHPUnit_Extensions_Database_DB_IDatabaseConnection once per test
    private $conn = null;

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
    	return $this->createFlatXmlDataSet('tests/dataset.xml');
    }

    public function setUp()
    {
    	parent::setUp();
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
		Model_PresenceFactory::setDatabase(self::$pdo);
    }

    public function testCreateNewSinaWeiboPresenceReturnsFalseWhenInvalid()
    {
    	$this->assertFalse(Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'cgnetrhuickmger', false, false));
    }

    public function testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid()
    {
    	$this->assertTrue(Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'learnenglish', false, false));
    	$this->assertEquals(1, $this->getConnection()->getRowCount('presences'), "Inserting failed");
    }

    public function testCreatingNewSinaWeiboPresenceInsertsCorrectValues()
    {
    	Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
    	$provider = new Model_SinaWeiboProvider(self::$pdo);
    	$data = $provider->testHandle('learnenglish');
    	$dbdata = self::$pdo->query("SELECT * FROM `presences` WHERE `handle` = 'learnenglish'");
    	$dbdata = $dbdata->fetch(PDO::FETCH_ASSOC);

    	$this->assertEquals($data[0], $dbdata['type']);
    	$this->assertEquals($data[1], $dbdata['handle']);
    	$this->assertEquals($data[2], $dbdata['uid']);
    	$this->assertEquals($data[3], $dbdata['image_url']);
    	$this->assertEquals($data[4], $dbdata['name']);
    	$this->assertEquals($data[5], $dbdata['page_url']);
    	$this->assertEquals($data[6], $dbdata['popularity']);
    }

    /**
     * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
     */
    public function testFetchPresenceByHandleHasCorrectHandle()
    {
    	Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
    	$presence = Model_PresenceFactory::getPresenceByHandle('learnenglish');
    	$this->assertEquals('learnenglish', $presence->getHandle());
    }
}