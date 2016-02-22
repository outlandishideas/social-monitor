<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

use Model_Presence;

class Handle extends Header {

    protected static $name = "handle";

    function __construct()
    {
        $this->label = 'Handle';
        $this->description = 'Select all the presences that you would like to compare, and then click on the Compare Button above';
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE);
        $this->cellClasses[] = 'left-align';
    }

    /**
     * @param Model_Presence $model
     * @return mixed|string
     */
    public function getTableCellValue($model)
    {
        $handle = $this->getValue($model);
        $sign = $model->getPresenceSign();
        $engagment = $model->getEngagementScore();
        $value = "<span class=\"$sign fa-lg fa-fw\"></span> $handle";
        $value .= " <span class=\"engagement-score {$engagment->getType()}\" title=\"{$engagment->getName()}: {$engagment->getScore()}\">" . round($engagment->getScore()) . '</span>';

        return $value;
    }

    /**
     * @param Model_Presence $model
     * @return null
     */
    function getValue($model = null)
    {
        return $model->getHandle();
    }


}