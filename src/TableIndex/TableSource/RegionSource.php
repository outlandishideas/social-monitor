<?php

namespace Outlandish\SocialMonitor\TableIndex\TableSource;

use Model_Region;

class RegionSource extends TableSource
{
    function getTableData()
    {
        return Model_Region::fetchAll();
    }
}