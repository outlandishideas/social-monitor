<?php

namespace Outlandish\SocialMonitor\Report;

use Model_Country;

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

    public function getCampaignTypes()
    {
        return [1];
    }

    public function getBaseType()
    {
        return 'country';
    }
}