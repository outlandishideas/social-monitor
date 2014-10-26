<?php

abstract class CampaignController extends GraphingController
{
    public function managePresencesList()
    {
        $presences = array();
        foreach(NewModel_PresenceType::enumValues() as $type) {
            /** @var NewModel_PresenceType $type */
            $presences[] = array(
                'type' => $type->getValue(),
                'title' => "Available " . $type->getTitle() . " Presences",
                'presences' => NewModel_PresenceFactory::getPresencesByType($type),
                'sign' => $type->getSign()
            );
        }
        return $presences;
    }
}

