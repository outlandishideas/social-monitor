<?php

namespace Outlandish\SocialMonitor\TableIndex\TableSource;

use Model_PresenceFactory;
use Zend_Registry;

class PresenceSource extends TableSource
{
    function getTableData()
    {
        return Model_PresenceFactory::getPresences();
    }
}