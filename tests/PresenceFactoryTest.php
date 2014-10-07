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

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresenceByIdHasCorrectHandle()
	{
		Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		$presence = Model_PresenceFactory::getPresenceById(1);
		$this->assertEquals('learnenglish', $presence->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByType()
	{
		Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'invalid', false, false);
		$presences = Model_PresenceFactory::getPresencesByType(Model_PresenceType::SINA_WEIBO());
		$this->assertEquals(2, count($presences));
		$this->assertEquals('invalid', $presences[0]->getHandle());
		$this->assertEquals('learnenglish', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesById()
	{
		Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$presences = Model_PresenceFactory::getPresencesById(array(1,2));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByCampaign()
	{
		Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(Model_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$stmt = self::$pdo->prepare("INSERT INTO `campaign_presences` (campaign_id, presence_id) VALUES(:cid,:pid1),(:cid,:pid2)");
		$stmt->execute(array(':cid' => 1, ':pid1' => 1, ':pid2' => 2));

		$campaign = 1;

		$presences = Model_PresenceFactory::getPresencesByCampaign($campaign);

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}


}