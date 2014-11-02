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
}