<?php

class Chart_PopularityTrend extends Chart_Abstract {

    protected static $title = "Measure: Popularity Trend";
    protected static $name = "popularity-trend";

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
            "label" => 'Number of Fans/Followers Gained Per Day',
            "position" => 'outer-middle',
         );
    }

    protected function getData($model, DateTime $start, DateTime $end)
    {
        // subtract 1 from the first day, as we're calculating a daily difference
        $start = clone $start;
        $start->modify('-1 day');

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
            while ($start <= $end) {
                $dates[] = $start->format('Y-m-d');
                $start->modify('1 day');
            }
            foreach ($dataSets as $key=>$dataSet) {
                $popularity = array();
                foreach ($dataSet as $row) {
                    $date = date('Y-m-d', strtotime($row['datetime']));
                    if (!array_key_exists($date, $popularity)) {
                        $popularity[$date] = floatval($row['value']);
                    }
                }
                $trend = array($key);
                for ($i=1; $i<count($dates); $i++) {
                    if (isset($popularity[$dates[$i]]) && isset($popularity[$dates[$i-1]])) {
                        $trend[] = $popularity[$dates[$i]] - $popularity[$dates[$i-1]];
                    } else {
                        $trend[] = null;
                    }
                }
                $columns[] = $trend;
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

}