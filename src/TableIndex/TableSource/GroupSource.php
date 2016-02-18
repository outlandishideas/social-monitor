<?php

namespace Outlandish\SocialMonitor\TableIndex\TableSource;

use Model_Group;

class GroupSource extends TableSource
{
    function getTableData()
    {
        return Model_Group::fetchAll();
    }
}