<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 07/10/2014
 * Time: 17:20
 */


require_once 'vendor/autoload.php';

class NewModel_PresenceTest extends PHPUnit_Extensions_Database_TestCase {

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
        return $this->createFlatXmlDataSet('tests/datasetWithPresences.xml');
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
        $this->presence = NewModel_PresenceFactory::getPresenceById(1);
    }

    public function test_get_id()
    {
        $this->assertEquals(1, $this->presence->getId());
    }

    public function test_get_owner()
    {
        $owner = $this->presence->getOwner();
        $this->assertInstanceOf("Model_Country", $owner);
        $this->assertEquals(1, $owner->id);
    }

    public function test_get_owner_if_owner_is_group()
    {
        $presence = NewModel_PresenceFactory::getPresenceById(2);
        $owner = $presence->getOwner();
        $this->assertInstanceOf("Model_Group", $owner);
        $this->assertEquals(2, $owner->id);
    }
}
 