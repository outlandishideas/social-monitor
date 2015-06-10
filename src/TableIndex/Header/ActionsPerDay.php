<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class ActionsPerDay extends Header {

    protected static $name = "actions-per-day";

    function __construct()
    {
        $this->label = "Actions per Day";
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE, self::MODEL_TYPE_CAMPAIGN);
    }

    /**
     * @param \Model_Presence|\Model_Campaign $model
     * @return string
     */
    function getValue($model = null)
    {
        $actual = null;
        $end = new \DateTime();
        $start = clone $end;
        $start = $start->sub(\DateInterval::createFromDateString('30 days'));
        if ($model instanceof \Model_Presence) {
            $data = $model->getHistoricStreamMeta($start, $end, true);
            if(count($data) > 0){
                $actual = 0;
                foreach ($data as $row) {
                    $actual += $row['number_of_actions'];
                }
                $actual /= count($data);
            }
        } else if ($model instanceof \Model_Campaign) {
            $actual = 0;
            $count = 0;
            foreach ($model->getPresences() as $p) {
                $v = $this->getValue($p);
                if (is_null($v)) continue;
                $actual += $v;
                $count++;
            }
            if ($count == 0) {
                return null;
            }
            $actual /= $count;
        } else {
            throw new RuntimeException("Invalid model");
        }
        return $actual;
    }

    function formatValue($value)
    {
        if (is_numeric($value)) {
            return number_format(round($value, 2), 2);
        }
        return self::NO_VALUE;
    }

}