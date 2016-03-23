<?php

use Outlandish\SocialMonitor\PresenceType\PresenceType;

abstract class CampaignController extends GraphingController
{
    public function managePresencesList()
    {
        $presences = array();
        foreach(PresenceType::getAll() as $type) {
            $presences[] = array(
                'type' => $type->getValue(),
                'title' => $type->getTitle(),
                'presences' => Model_PresenceFactory::getPresencesByType($type),
                'sign' => $type->getSign()
            );
        }
        return $presences;
    }
}

