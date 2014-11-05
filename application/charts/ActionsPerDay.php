<?php

class Chart_ActionsPerDay extends Chart_Abstract {

    protected static $title = "Measure: Actions per Day";
    protected static $name = "actionsPerDay";

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
            "label" => 'Number of Actions Per Day',
            "position" => 'outer-middle',
         );
    }

    protected function getData($model, DateTime $start, DateTime $end)
    {
        $names = array();
        $dataSets = array();
        if (!($model instanceof Model_Presence)) {
            return array();
        }

        /** @var Model_Presence $model */
        $data = $model->getActionsPerDayData($start, $end);
        if ($data) {
            $key = Metric_ActionsPerDay::getName();
            $key2 = 'relevant';
            $names[$key] = Metric_ActionsPerDay::getTitle();
            $names[$key2] = 'Relevant links per day';
            $dataSets[$key] = $data;
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
                $actions = array();
                $relevant = array();
                foreach ($dates as $date) {
                    $actions[$date] = 0;
                    $relevant[$date] = 0;
                }
                foreach ($dataSet as $row) {
                    $date = date('Y-m-d', strtotime($row['date']));
                    $actions[$date] = $row['number_of_actions'] ? $row['number_of_actions'] : 0;
                    $relevant[$date] = $row['number_of_bc_links'] ? $row['number_of_bc_links'] : 0;
                }
                $actions = array_values($actions);
                $relevant = array_values($relevant);
                array_unshift($actions, $key);
                array_unshift($relevant, $key2);
                $columns[] = $actions;
                $columns[] = $relevant;
            }
            array_unshift($dates, 'date');
            $columns[] = $dates;
        }

        return array(
            "x" => 'date',
            "type" => 'bar',
            "columns" => $columns,
            "names" => $names
        );
    }
}