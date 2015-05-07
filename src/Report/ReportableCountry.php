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
use Outlandish\SocialMonitor\Query\BadgeRankQuerier;

class ReportableCountry implements Reportable
{

    /**
     * @var Model_Country
     */
    private $country;

    public function __construct(Model_Country $country)
    {
        $this->country = $country;
    }

    public function getType()
    {
        return "Country";
    }

    public function getName()
    {
        return $this->country->getName();
    }

    public function getIcon()
    {
        return "fa-flag";
    }

    public function getColumn()
    {
        return "campaign_id";
    }

    public function getId()
    {
        return $this->country->id;
    }

    public function numberOfType()
    {
        return Model_Country::countAll();
    }
}