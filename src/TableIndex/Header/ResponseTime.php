<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

class ResponseTime extends Header {

    protected static $name = "response-time";

    function __construct($translator)
    {
        parent::__construct($translator);
        $this->sort = self::SORT_TYPE_NUMERIC_FUZZY;
        $this->allowedTypes = array(self::MODEL_TYPE_PRESENCE, self::MODEL_TYPE_CAMPAIGN);
    }

    /**
     * @param \Model_Presence|\Model_Campaign $model
     * @return float
     */
    function getValue($model = null)
    {
        $actual = null;
        $end = new \DateTime();
        $start = clone $end;
        $start = $start->sub(\DateInterval::createFromDateString('30 days'));
        if ($model instanceof \Model_Presence) {
            $data = $model->getResponseData($start, $end);
            if (is_null($data)) return null;
            if (!$data || empty($data)) return null;
            $total = 0;
            foreach ($data as $d) {
                $total += $d->diff;
            }
            $actual = $total/count($data);
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
            throw new \RuntimeException("Invalid model");
        }
        return $actual;
    }

    function formatValue($value)
    {
        if (is_numeric($value)) {
            return number_format(round($value, 2), 2).' hours';
        }
        return self::NO_VALUE;
    }
}