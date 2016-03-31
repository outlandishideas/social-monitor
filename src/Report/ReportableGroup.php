<?php

namespace Outlandish\SocialMonitor\Report;

use Model_Group;
use Symfony\Component\Translation\Translator;

class ReportableGroup implements Reportable
{

    /**
     * @var Model_Group
     */
    private $group;
	private $type;

    public function __construct(Model_Group $group, Translator $translator)
    {
        $this->group = $group;
		$this->type = $translator->trans('Global.group');
    }

    public function getType()
    {
        return $this->type;
    }

    public function getName()
    {
        return $this->group->name;
    }

    public function getIcon()
    {
        return "fa-th-large";
    }

    public function getColumn()
    {
        return "campaign_id";
    }

    public function getId()
    {
        return $this->group->id;
    }

    public function numberOfType()
    {
        return Model_Group::countAll();
    }

    public function getCampaignTypes()
    {
        return [0];
    }

    public function getBaseType()
    {
        return 'group';
    }
}