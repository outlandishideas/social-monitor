<?php

class Chart_Popularity extends Chart_Abstract {

    protected static $title = "Measure: Popularity";
    protected static $name = "popularity";

    protected function getXAxis()
    {
        return array(
            "type" => 'timeseries',
            "label" => 'Date',
            "position" => 'outer-center'
        );
    }

    protected function getYAxis()
    {
        return array(
            "label" => 'Number of Fans/Followers',
            "position" => 'outer-middle',
         );
    }

    protected function getData($model, DateTime $start, DateTime $end)
    {
        $names = array();
        $dataSets = array();
        switch(get_class($model)) {
            case "NewModel_Presence":
                /** @var NewModel_Presence $model */
                $data = $model->getPopularityData($start, $end);
                if ($data) {
                    $key = Metric_Popularity::getName();
                    $names[$key] = Metric_Popularity::getTitle();
                    $dataSets[$key] = $data;
                }
                break;
            case "Model_Country":
            case "Model_Group":
            case "Model_Region":
                /** @var Model_Campaign $model */
                foreach ($model->getPresences() as $presence) {
                    $data = $presence->getPopularityData($start, $end);
                    if ($data) {
                        $dataSets[$presence->getId()] = $data;
                        $names[$presence->getId()] = $presence->getName();
                    }
                }
                break;
            default:
                return array();
        }

        $columns = array();

        if ($dataSets) {
            $dates = array();
            $current = clone $start;
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('1 day');
            }
            foreach ($dataSets as $key=>$dataSet) {
                $popularity = array();
                foreach ($dates as $date) {
                    $popularity[$date] = null;
                }
                foreach ($dataSet as $row) {
                    $datetime = $row['datetime'];
                    $date = date('Y-m-d', strtotime($datetime));
                    if (empty($popularity[$date])) {
                        $popularity[$date] = floatval($row['value']);
                    }
                }
                $popularity = array_values($popularity);
                array_unshift($popularity, $key);
                $columns[] = $popularity;
            }
            array_unshift($dates, 'date');
            $columns[] = $dates;
        }

        return array(
            "x" => 'date',
            "columns" => $columns,
            "names" => $names
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

/*
    private function generatePopularityGraphData($presence, $startDate, $endDate)
    {
        // subtract 1 from the first day, as we're calculating a daily difference
        $startDate = date('Y-m-d', strtotime($startDate . ' -1 day'));

        $data = $presence->getPopularityData($startDate, $endDate);
        $points = array();
        $target = $presence->getTargetAudience();
        $targetDate = $presence->getTargetAudienceDate($startDate, $endDate);
        $graphHealth = 100;
        $requiredRates = null;
        $timeToTarget = null;

        if ($data) {
            $current = $data[count($data)-1];

            $targetDiff = $target - $current->value;

            $bestScore = BaseController::getOption('achieve_audience_best');
            $goodScore = BaseController::getOption('achieve_audience_good');
            $badScore = BaseController::getOption('achieve_audience_bad');

            $daysPerMonth = 365/12;
            $bestRate = $targetDiff/($daysPerMonth*$bestScore);
            $goodRate = $targetDiff/($daysPerMonth*$goodScore);
            $badRate = $targetDiff/($daysPerMonth*$badScore);

            $healthCalc = function($value) use ($bestRate, $goodRate, $badRate, $targetDiff) {
                if ($targetDiff < 0 || $value >= $bestRate) {
                    return 100;
                } else if ($value < 0 || $value <= $badRate) {
                    return 0;
                } else if ($value >= $goodRate) {
                    return 50 + 50*($value - $goodRate)/($bestRate - $goodRate);
                } else {
                    return 50*($value - $badRate)/($goodRate - $badRate);
                }
            };
            $requiredRates = array();
            if ($bestRate > 0) {
                $requiredRates[] = array('rate'=>$bestRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $bestScore . ' months')));
            }
            if ($goodRate > 0) {
                $requiredRates[] = array('rate'=>$goodRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $goodScore . ' months')));
            }
            if ($badRate > 0) {
                $requiredRates[] = array('rate'=>$badRate, 'date'=>date('F Y', strtotime($current->datetime . ' +' . $badScore . ' months')));
            }

            if ($targetDiff > 0) {
                if ($targetDate) {
                    $interval = date_create($targetDate)->diff(date_create($startDate));
                    $timeToTarget = array('y'=>$interval->y, 'm'=>$interval->m);
                    $graphHealth = $healthCalc($targetDiff/$interval->days);
                }
            } else {
                $graphHealth = 100;
            }

            foreach ($data as $point) {
                $key = gmdate('Y-m-d', strtotime($point->datetime));
                $points[$key] = $point->value; // overwrite any previous value, as data is sorted by datetime ASC
            }

            foreach ($points as $key=>$value) {
                $points[$key] = (object)array('date'=>$key, 'total'=>$value);
            }

            foreach ($points as $key=>$point) {
                $prevDay = date('Y-m-d', strtotime($key . ' -1 day'));
                if (array_key_exists($prevDay, $points)) {
                    $point->value = $point->total - $points[$prevDay]->total;
                    $point->health = $healthCalc($point->value);
                } else {
                    $point->value = 0;
                }
            }

            $points = $this->fillDateGaps($points, $startDate, $endDate, 0);
            $points = array_values($points);

            $current = array(
                'value'=>$current->value,
                'date'=>gmdate('d F Y', strtotime($current->datetime))
            );
        } else {
            $current = null;
        }

        return array(
            'target' => $target,
            'timeToTarget' => $timeToTarget,
            'points' => $points,
            'current' => $current,
            'health' => $graphHealth,
            'requiredRates' => $requiredRates
        );
    }

    private function generatePostsPerDayGraphData($presence, $startDate, $endDate) {
        $postsPerDay = $presence->getPostsPerDayData($startDate, $endDate);
        usort($postsPerDay, function($a, $b) { return strcmp($a->date, $b->date); });

        $target = BaseController::getOption('updates_per_day');
        $average = 0;
        if ($postsPerDay) {
            $total = 0;
            foreach ($postsPerDay as $row) {
                $total += $row->value;
            }
            $average = $total/count($postsPerDay);
        }

        $relevance = array();
        foreach ($postsPerDay as $entry) {
            $date = $entry->date;
            $relevance[$date] = (object)array('date'=>$date, 'value'=>0, 'subtitle'=>'Relevance', 'statusIds'=>array());
        }

        foreach ($presence->getRelevanceData($startDate, $endDate) as $row) {
            $relevance[$row->created_time]->value = $row->total_bc_links;
        }
        $rAverage = 0;
        foreach($relevance as $r){
            $rAverage += $r->value;
        }
        $rAverage /= count($relevance);

        $relevancePercentage = $presence->isForFacebook() ? 'facebook_relevance_percentage' : 'twitter_relevance_percentage';

        return array(
            'average' => $average,
            'rAverage' => $rAverage,
            'target' => $target,
            'rTarget' => ($target/100)*BaseController::getOption($relevancePercentage),
            'points' => $postsPerDay,
            'relevance' => array_values($relevance),
        );
    }

*/
}