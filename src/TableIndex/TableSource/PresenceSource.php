<?php

namespace Outlandish\SocialMonitor\TableIndex\TableSource;

use Model_PresenceFactory;
use Zend_Registry;

class PresenceSource extends TableSource
{
    function getTableData()
    {
        Model_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
        return Model_PresenceFactory::getPresences();
    }
}