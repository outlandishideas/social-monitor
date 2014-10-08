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
		$resourceLoader->addResourceType('refactored', 'models/refactored/', 'NewModel');
		$resourceLoader->addResourceType('metric', 'metrics/', 'Metric');
		NewModel_PresenceFactory::setDatabase(self::$pdo);
    }

    public function testCreateNewSinaWeiboPresenceReturnsFalseWhenInvalid()
    {
    	$this->assertFalse(NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'cgnetrhuickmger', false, false));
    }

    public function testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid()
    {
    	$this->assertTrue(NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false));
    	$this->assertEquals(1, $this->getConnection()->getRowCount('presences'), "Inserting failed");
    }

    public function testCreatingNewSinaWeiboPresenceInsertsCorrectValues()
    {
    	NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
    	$provider = new NewModel_SinaWeiboProvider(self::$pdo);
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
    	NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
    	$presence = NewModel_PresenceFactory::getPresenceByHandle('learnenglish', NewModel_PresenceType::SINA_WEIBO());
    	$this->assertEquals('learnenglish', $presence->getHandle());
    }

	public function testFetchPresenceByHandleReturnsNullWhenHandleNotInDb()
	{
		$this->assertNull(NewModel_PresenceFactory::getPresenceByHandle('learnenglish', NewModel_PresenceType::SINA_WEIBO()));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresenceByHandleReturnsCorrectPresenceWhenMultipleExist()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::FACEBOOK(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		$presence = NewModel_PresenceFactory::getPresenceByHandle('learnenglish', NewModel_PresenceType::SINA_WEIBO());
		$this->assertEquals('learnenglish', $presence->getHandle());
		$this->assertEquals(NewModel_PresenceType::SINA_WEIBO, $presence->getType());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresenceByIdHasCorrectHandle()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		$presence = NewModel_PresenceFactory::getPresenceById(1);
		$this->assertEquals('learnenglish', $presence->getHandle());
	}

	public function testFetchPresenceByIdReturnsNullWhenIdNotInDb()
	{
		$this->assertNull(NewModel_PresenceFactory::getPresenceById(1));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByType()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);
		$presences = NewModel_PresenceFactory::getPresencesByType(NewModel_PresenceType::SINA_WEIBO());
		$this->assertEquals(2, count($presences));
		$this->assertEquals('invalid', $presences[0]->getHandle());
		$this->assertEquals('learnenglish', $presences[1]->getHandle());
	}

	public function testFetchPresencesByTypeReturnsEmptyArrayWhenTypeNotInDb()
	{
		$this->assertEmpty(NewModel_PresenceFactory::getPresencesByType(NewModel_PresenceType::SINA_WEIBO()));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesById()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$presences = NewModel_PresenceFactory::getPresencesById(array(1,2));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('invalid', $presences[0]->getHandle());
		$this->assertEquals('learnenglish', $presences[1]->getHandle());
	}

	public function testFetchPresencesByIdReturnsEmptyArrayWhenIdsNotInDb()
	{
		$this->assertEmpty(NewModel_PresenceFactory::getPresencesById(array(1,2)));
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByCampaign()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$stmt = self::$pdo->prepare("INSERT INTO `campaign_presences` (campaign_id, presence_id) VALUES(:cid,:pid1),(:cid,:pid2)");
		$stmt->execute(array(':cid' => 1, ':pid1' => 1, ':pid2' => 2));

		$campaign = 1;

		$presences = NewModel_PresenceFactory::getPresencesByCampaign($campaign);

		$this->assertEquals(2, count($presences));
		$this->assertEquals('invalid', $presences[0]->getHandle());
		$this->assertEquals('learnenglish', $presences[1]->getHandle());
	}


	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByTypeDesc()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);
		$presences = NewModel_PresenceFactory::getPresencesByType(NewModel_PresenceType::SINA_WEIBO(), array('orderDirection' => 'DESC'));
		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByIdDesc()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$presences = NewModel_PresenceFactory::getPresencesById(array(1,2), array('orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByCampaignDesc()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$stmt = self::$pdo->prepare("INSERT INTO `campaign_presences` (campaign_id, presence_id) VALUES(:cid,:pid1),(:cid,:pid2)");
		$stmt->execute(array(':cid' => 1, ':pid1' => 1, ':pid2' => 2));

		$campaign = 1;

		$presences = NewModel_PresenceFactory::getPresencesByCampaign($campaign, array('orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}


	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByTypePopularity()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);
		$presences = NewModel_PresenceFactory::getPresencesByType(NewModel_PresenceType::SINA_WEIBO(), array('orderColumn' => 'p.popularity', 'orderDirection' => 'DESC'));
		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByIdPopularity()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$presences = NewModel_PresenceFactory::getPresencesById(array(1,2), array('orderColumn' => 'p.popularity', 'orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}

	/**
	 * @depends testCreateNewSinaWeiboPresenceGetsAddedToDBWhenValid
	 */
	public function testFetchPresencesByCampaignPopularity()
	{
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'learnenglish', false, false);
		NewModel_PresenceFactory::createNewPresence(NewModel_PresenceType::SINA_WEIBO(), 'invalid', false, false);

		$stmt = self::$pdo->prepare("INSERT INTO `campaign_presences` (campaign_id, presence_id) VALUES(:cid,:pid1),(:cid,:pid2)");
		$stmt->execute(array(':cid' => 1, ':pid1' => 1, ':pid2' => 2));

		$campaign = 1;

		$presences = NewModel_PresenceFactory::getPresencesByCampaign($campaign, array('orderColumn' => 'p.popularity', 'orderDirection' => 'DESC'));

		$this->assertEquals(2, count($presences));
		$this->assertEquals('learnenglish', $presences[0]->getHandle());
		$this->assertEquals('invalid', $presences[1]->getHandle());
	}


}