<?php

namespace Outlandish\SocialMonitor\Report;

use Model_Region;

class ReportableRegion implements Reportable
{

    /**
     * @var Model_Region
     */
    private $region;

    public function __construct(Model_Region $region)
    {
        $this->region = $region;
    }

    public function getType()
    {
        return "Region";
    }

    public function getName()
    {
        return $this->region->name;
    }

    public function getIcon()
    {
        return "fa-globe";
    }

    public function getColumn()
    {
        return "region_id";
    }

    public function getId()
    {
        return $this->region->id;
    }

    public function numberOfType()
    {
        return Model_Region::countAll();
    }

    public function getCampaignTypes()
    {
        return [0,1,2];
    }

    public function getBaseType()
    {
        return 'region';
    }
}