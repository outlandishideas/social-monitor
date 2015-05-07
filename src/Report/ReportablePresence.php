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
use Model_Presence;
use Model_PresenceFactory;
use Outlandish\SocialMonitor\Query\BadgeRankQuerier;

class ReportablePresence implements Reportable
{

    /**
     * @var Model_Presence
     */
    private $presence;

    public function __construct(Model_Presence $presence)
    {
        $this->presence = $presence;
    }

    public function getType()
    {
        return "Presence";
    }

    public function getName()
    {
        return $this->presence->getName();
    }

    public function getIcon()
    {
        return $this->presence->getPresenceSign();
    }

    public function getPresenceIds()
    {
        return [$this->presence->getId()];
    }

    public function getColumn()
    {
        return "presence_id";
    }

    public function getId()
    {
        return $this->presence->getId();
    }

    public function numberOfType()
    {
        return count(Model_PresenceFactory::getPresences());
    }
}