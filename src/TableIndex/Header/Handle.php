<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

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
        $value = "<span class=\"$sign fa-lg fa-fw\"></span> $handle";
        if ($model->isForFacebook()) {
            $score = $model->getFacebookEngagement();
            $value .= " <span class=\"engagement-score facebook\" title=\"Facebook engagement score: $score\">" . round($score) . '</span>';
        } else if ($model->isForTwitter()) {
            $score = $model->getKloutScore();
            $value .= " <span class=\"engagement-score klout\" title=\"Klout score: $score\">" . round($score) . '</span>';
        } else if ($model->isForSinaWeibo()) {
            $score = $model->getSinaWeiboEngagement();
            $value .= " <span class=\"engagement-score sina-weibo\" title=\"Sina Weibo engagement score: $score\">" . round($score) . '</span>';
        }
        if (!$model->getUID()) {
            $value = '<span class="missing" title="Presence not found">' . $value . '</span>';
        }
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