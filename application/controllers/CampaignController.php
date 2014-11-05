<?php

abstract class CampaignController extends GraphingController
{
    public function managePresencesList()
    {
        $presences = array();
        foreach(Enum_PresenceType::enumValues() as $type) {
            /** @var Enum_PresenceType $type */
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

