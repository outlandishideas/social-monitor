<?php

class Metric_PopularityTime extends Metric_Abstract {

    protected static $name = "popularity_time";
    protected static $title = "Popularity Trend";
    protected static $icon = "fa fa-line-chart";

    function __construct()
    {
        $this->target = floatval(BaseController::getOption('achieve_audience_good'));
    }

    /**
     * Counts the months between now and estimated date of reaching target audience
     * calculates score based on
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return null/string
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $estimate = $this->getTargetAudienceDate($presence, $start, $end);
        $actualMonths = null;
        if ($estimate instanceof \DateTime) {
            $now = new DateTime('now');
            $now->setTime(0,0,0);
            $diff = $estimate->diff($now);
            $actualMonths = $diff->y*12 + $diff->m + $diff->d/30;
        }
        return $actualMonths;
    }

    /**
     * Gets the date at which the target audience size will be reached, based on the trend over the given time period.
     * If the target is already reached, or there is no target, this will return null.
     * If any of these conditions are met, this will return the maximum date possible:
     * - popularity has never varied
     * - the calculated date is in the past
     * - there are fewer than 2 data points
     * - the calculated date would be too far in the future (32-bit date problem)
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return null|DateTime
     */
    protected function getTargetAudienceDate(Model_Presence $presence, DateTime $start, DateTime $end)
    {
        $date = new DateTime; //the return value

        $target = $presence->getTargetAudience();
        $popularity = $presence->getPopularity();



        if(is_numeric($target) && $target > 0 && $popularity < $target) {

            $data = $presence->getHistoricData($start, $end, Metric_Popularity::getName());
            $count = count($data);

            if ($count > 1) {
                // calculate line of best fit (see http://www.endmemo.com/statistics/lr.php)
                $meanX = $meanY = $sumXY = $sumXX = 0;

                $factor = 1;
                foreach ($data as $row) {
                    // scale x down by a large factor, to avoid large number issues
                    $rowDate = strtotime($row['datetime'])/$factor;
                    $meanX += $rowDate;
                    $meanY += $row['value'];
                    $sumXY += $rowDate*$row['value'];
                    $sumXX += $rowDate*$rowDate;
                }

                $meanX /= $count;
                $meanY /= $count;

                $denominator = ($sumXX - $count*$meanX*$meanX);
                $numerator = ($sumXY - $count*$meanX*$meanY);

                if ($denominator != 0 && $numerator/$denominator > 0) {
                    $gradient = $factor * $numerator/$denominator;
                    $intersect = $meanY - $factor * $gradient * $meanX;
                    $timestamp = ($target - $intersect)/$gradient;
                    if ($timestamp < PHP_INT_MAX) {
                        //we've been having some difficulties with DateTime and
                        //large numbers. Try to run a DateTime construct to see if it works
                        //if not nullify $date so that we can create a DateTime from PHP_INI_MAX
                        try {
                            $date = new DateTime();
                            $date->setTimestamp(round($timestamp));
                        } catch (Exception $e) {
                            $date = null;
                        }
                    }
                }
            }

            if (!($date instanceof DateTime) || $date->getTimestamp() < time()) {
                try {
                    $date = new DateTime(PHP_INT_MAX);
                } catch (Exception $e) {
                    $date = null;
                }
            }
        }

        if ($date instanceof DateTime) {
            $date->setTime(0,0,0);
        }
        return $date;
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = null;

        if ($this->target > 0) {
            $current = $presence->getMetricValue($this);

            if($current > 0){
                $score = round(100 * $this->target / $current);
                $score = self::boundScore($score);
            } else if ($current === 0 || $current === '0') {
                // target is already reached
                $score = 100;
            }
        }

        return $score;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
    }


}