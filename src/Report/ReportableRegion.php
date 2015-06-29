<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 07/05/2015
 * Time: 16:05
 */

namespace Outlandish\SocialMonitor\Report;


use Badge_Factory;
use Badge_Total;
use DateTime;
use Enum_Period;
use Model_Country;
use Model_PresenceFactory;
use Model_Region;
use Outlandish\SocialMonitor\Query\BadgeRankQuerier;

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