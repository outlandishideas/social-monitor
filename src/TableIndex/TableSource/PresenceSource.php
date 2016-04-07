<?php

namespace Outlandish\SocialMonitor\TableIndex\TableSource;

use Model_PresenceFactory;

class PresenceSource extends TableSource
{
    function getTableData()
    {
        return Model_PresenceFactory::getPresences();
    }
}