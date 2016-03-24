<?php

namespace Outlandish\SocialMonitor\Report;

use Model_Presence;
use Model_PresenceFactory;
use Symfony\Component\Translation\Translator;

class ReportablePresence implements Reportable
{

    /**
     * @var Model_Presence
     */
    private $presence;
	private $type;

    public function __construct(Model_Presence $presence, Translator $translator)
    {
        $this->presence = $presence;
		$this->type = $translator->trans('Global.presence');
	}

    public function getType()
    {
        return $this->type;
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

    public function getCampaignTypes()
    {
        return [0,1,2];
    }

    public function getBaseType()
    {
        return 'presence';
    }
}