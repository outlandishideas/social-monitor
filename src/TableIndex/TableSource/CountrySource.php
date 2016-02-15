<?php

namespace Outlandish\SocialMonitor\TableIndex\TableSource;

use Model_Country;

class CountrySource extends TableSource
{
    function getTableData()
    {
        return Model_Country::fetchAll();
    }
}