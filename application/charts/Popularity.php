<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

class Chart_Popularity extends Chart_Abstract {

    protected static $title = "Measure: Popularity";
    protected static $description;
    protected static $name = "popularity";

    protected $xLabel = "Date";
    protected $yLabel = "Number of Fans/Followers Gained Per Day";

    protected function getXAxis()
    {
        return array(
            "type" => 'timeseries',
            "label" => $this->getXLabel(),
            "position" => 'outer-center'
        );
    }

    protected function getYAxis()
    {
        return array(
            "label" => $this->getYLabel(),
            "position" => 'outer-middle',
         );
    }

    protected function getNames($data = null)
    {
        return array(Metric_Popularity::getName(), Metric_Popularity::getTitle());
    }

    protected function getColumns($data = null)
    {

    }

    public function getXColumn()
    {
        return "date";
    }

    protected function getData($model, DateTime $start, DateTime $end)
    {
        return array(
            "x" => $this->getXColumn(),
            "columns" => array(
                array("date", "2014-10-01", "2014-10-02", "2014-10-03", "2014-10-04", "2014-10-05", "2014-10-06", "2014-10-07", "2014-10-08", "2014-10-09", "2014-10-10"),
                array("Something", 10, 9, 8, 7, 6, 5, 4, 3, 2, 1)
            ),
            "names" => $this->getNames(),
            "type" => "bar",
            "labels" => true
        );
    }

//    public function getData(NewModel_Presence $presence, DateTime $start, DateTime $end)
//    {
//        //remove 1 day from start as we are calculating daily difference so need one more day
//        $start->modify('-1 day');
//        $data = $presence->getPopularityData($start, $end);
//
//        if ($data) {
//
//            //get difference between current and target
//            //note that current does not always equal the current date
//            $target = $presence->getTargetAudience();
//            $current = $data[count($data)-1];
//            $targetDiff = $target - $current->value;
//
//            //get the different targets (best, good, bad)
//            $bestScore = BaseController::getOption('achieve_audience_best');
//            $goodScore = BaseController::getOption('achieve_audience_good');
//            $badScore = BaseController::getOption('achieve_audience_bad');
//
//            $daysPerMonth = 365/12;
//            $bestRate = $targetDiff/($daysPerMonth*$bestScore);
//            $goodRate = $targetDiff/($daysPerMonth*$goodScore);
//            $badRate = $targetDiff/($daysPerMonth*$badScore);
//
//            //refactor this to be a separate function probably
//            $healthCalc = function($value) use ($bestRate, $goodRate, $badRate, $targetDiff) {
//                if ($targetDiff < 0 || $value >= $bestRate) {
//                    return 100;
//                } else if ($value < 0 || $value <= $badRate) {
//                    return 0;
//                } else if ($value >= $goodRate) {
//                    return 50 + 50*($value - $goodRate)/($bestRate - $goodRate);
//                } else {
//                    return 50*($value - $badRate)/($goodRate - $badRate);
//                }
//            };
//
//            //what rates are required to meet the different targets
//            $requiredRates = array();
//            if ($bestRate > 0) {
//                $requiredRates[] = array('rate'=>$bestRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $bestScore . ' months')));
//            }
//            if ($goodRate > 0) {
//                $requiredRates[] = array('rate'=>$goodRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $goodScore . ' months')));
//            }
//            if ($badRate > 0) {
//                $requiredRates[] = array('rate'=>$badRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $badScore . ' months')));
//            }
//
//            //overall graph health
//            $targetDate = $presence->getTargetAudienceDate($start, $end);
//            if ($targetDiff > 0) {
//                if ($targetDate) {
//                    $interval = $targetDate->diff($start);
//                    $timeToTarget = array('y'=>$interval->y, 'm'=>$interval->m);
//                    $graphHealth = $healthCalc($targetDiff/$interval->days);
//                }
//            } else {
//                $graphHealth = 100;
//            }
//
//            //remove duplicate values on same day
//            $points = array();
//            foreach ($data as $row) {
//                $key = gmdate('Y-m-d', strtotime($row['datetime']));
//                if(!array_key_exists($key, $points)) {
//                    $points[$key] = array(
//                        'date' => $key,
//                        'total' => 0,
//                        'value' => 0,
//                        'health' => $healthCalc(0)
//                    );
//                }
//                $points[$key]['total'] = $row['value']; // overwrite any previous value, as data is sorted by datetime ASC
//            }
//
//            //calculate difference with previous day
//            foreach ($points as $key => $point) {
//                $prevDay = date('Y-m-d', strtotime($key . ' -1 day'));
//                if (array_key_exists($prevDay, $points)) {
//                    $point['value'] = $point['total'] - $points[$prevDay]['total'];
//                    $point['health'] = $healthCalc($point['value']);
//                }
//            }
//
//
//
//
//        }
//
//    }

}