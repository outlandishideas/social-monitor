<?php

use Outlandish\SocialMonitor\PresenceType\PresenceType;
use Outlandish\SocialMonitor\PresenceType\SinaWeiboType;

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
    	defined('APPLICATION_ENV')
    		|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?: 'prod'));
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
		Model_PresenceFactory::setDatabase(self::$pdo);
		Badge_Factory::setDB(self::$pdo);
		//load config
		if (!file_exists(APPLICATION_PATH . '/configs/config.yaml')) {
			die('Please copy configs/config.sample.yaml to configs/config.yaml');
		}
		$config = new Zend_Config_Yaml(
		    APPLICATION_PATH . '/configs/config.yaml',
		    APPLICATION_ENV
		);
		Zend_Registry::set('config', $config);
    }

    /**
     * @group sina_weibo
     * @group external_dependency
     */
    public function testCreateNewSinaWeiboPresenceReturnsFalseWhenInvalid()
    {
    	$this->assertNull(Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'cgnetrhuickmger', false, false));
    }

    /**
     * @group sina_weibo
     * @group external_dependency
     */
    public function testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid()
    {
    	$this->assertNotNull(Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false));
    	$this->assertEquals(1, $this->getConnection()->getRowCount('presences'), "Inserting failed");
    }

    /**
     * @group sina_weibo
     * @group external_dependency
     */
    public function testCreatingNewSinaWeiboPresenceInsertsCorrectValues()
    {
    	$presence = Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
    	$provider = new Provider_SinaWeibo(self::$pdo);
        $provider->updateMetadata($presence);
    	$dbdata = self::$pdo->query("SELECT * FROM `presences` WHERE `handle` = 'learnenglish'");
    	$dbdata = $dbdata->fetch(PDO::FETCH_ASSOC);

    	$this->assertEquals($presence->type->getValue(), $dbdata['type']);
    	$this->assertEquals($presence->handle, $dbdata['handle']);
    	$this->assertEquals($presence->uid, $dbdata['uid']);
    	$this->assertEquals($presence->image_url, $dbdata['image_url']);
    	$this->assertEquals($presence->name, $dbdata['name']);
    	$this->assertEquals($presence->page_url, $dbdata['page_url']);
    	$this->assertEquals($presence->popularity, $dbdata['popularity']);
    }

    /**
     * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
     * @group sina_Weibo
     * @group external_dependency
     */
    public function testFetchPresenceByHandleHasCorrectHandle()
    {
    	Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
    	$presence = Model_PresenceFactory::getPresenceByHandle('learnenglish', PresenceType::SINA_WEIBO());
    	$this->assertEquals('learnenglish', $presence->getHandle());
    }

    /**
     * @group sina_weibo
     */
	public function testFetchPresenceByHandleReturnsNullWhenHandleNotInDb()
	{
		$this->assertNull(Model_PresenceFactory::getPresenceByHandle('learnenglish', PresenceType::SINA_WEIBO()));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group facebook
	 * @group external_dependency
	 */
	public function testFetchPresenceByHandleReturnsCorrectPresenceWhenMultipleExist()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::FACEBOOK(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		$presence = Model_PresenceFactory::getPresenceByHandle('learnenglish', PresenceType::SINA_WEIBO());
		$this->assertEquals('learnenglish', $presence->getHandle());
		$this->assertEquals(SinaWeiboType::NAME, $presence->getType());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresenceByIdHasCorrectHandle()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		$presence = Model_PresenceFactory::getPresenceById(1);
		$this->assertEquals('learnenglish', $presence->getHandle());
	}

	public function testFetchPresenceByIdReturnsNullWhenIdNotInDb()
	{
		$this->assertNull(Model_PresenceFactory::getPresenceById(1));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByType()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);
		$presences = Model_PresenceFactory::getPresencesByType(PresenceType::SINA_WEIBO());
		$this->assertEquals(2, count($presences));
		$this->assertEquals('invalid', $presences[0]->getHandle());
		$this->assertEquals('learnenglish', $presences[1]->getHandle());
	}

	/**
	 * @group sina_weibo
	 */
	public function testFetchPresencesByTypeReturnsEmptyArrayWhenTypeNotInDb()
	{
		$this->assertEmpty(Model_PresenceFactory::getPresencesByType(PresenceType::SINA_WEIBO()));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesById()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$presences = Model_PresenceFactory::getPresencesById(array(1,2));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('invalid', $presences[0]->getHandle());
		$this->assertEquals('learnenglish', $presences[1]->getHandle());
	}

	public function testFetchPresencesByIdReturnsEmptyArrayWhenIdsNotInDb()
	{
		$this->assertEmpty(Model_PresenceFactory::getPresencesById(array(1,2)));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByCampaign()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$stmt = self::$pdo->prepare("INSERT INTO `campaign_presences` (campaign_id, presence_id) VALUES(:cid,:pid1),(:cid,:pid2)");
		$stmt->execute(array(':cid' => 1, ':pid1' => 1, ':pid2' => 2));

		$campaign = 1;

		$presences = Model_PresenceFactory::getPresencesByCampaign($campaign);

		$this->assertEquals(2, count($presences));
		$this->assertEquals('invalid', $presences[0]->getHandle());
		$this->assertEquals('learnenglish', $presences[1]->getHandle());
	}


	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByTypeDesc()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);
		$presences = Model_PresenceFactory::getPresencesByType(PresenceType::SINA_WEIBO(), array('orderDirection' => 'DESC'));
		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByIdDesc()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$presences = Model_PresenceFactory::getPresencesById(array(1,2), array('orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByCampaignDesc()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$stmt = self::$pdo->prepare("INSERT INTO `campaign_presences` (campaign_id, presence_id) VALUES(:cid,:pid1),(:cid,:pid2)");
		$stmt->execute(array(':cid' => 1, ':pid1' => 1, ':pid2' => 2));

		$campaign = 1;

		$presences = Model_PresenceFactory::getPresencesByCampaign($campaign, array('orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}


	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByTypePopularity()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);
		$presences = Model_PresenceFactory::getPresencesByType(PresenceType::SINA_WEIBO(), array('orderColumn' => 'p.popularity', 'orderDirection' => 'DESC'));
		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByIdPopularity()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$presences = Model_PresenceFactory::getPresencesById(array(1,2), array('orderColumn' => 'p.popularity', 'orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 * @group sina_weibo
	 * @group external_dependency
	 */
	public function testFetchPresencesByCampaignPopularity()
	{
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		Model_PresenceFactory::createNewPresence(PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$stmt = self::$pdo->prepare("INSERT INTO `campaign_presences` (campaign_id, presence_id) VALUES(:cid,:pid1),(:cid,:pid2)");
		$stmt->execute(array(':cid' => 1, ':pid1' => 1, ':pid2' => 2));

		$campaign = 1;

		$presences = Model_PresenceFactory::getPresencesByCampaign($campaign, array('orderColumn' => 'p.popularity', 'orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}


}