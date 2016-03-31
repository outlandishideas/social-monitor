<?php

class Chart_ResponseTime extends Chart_Abstract {

    const NAME = "response-time";

	public function __construct(PDO $db, $translator)
	{
		parent::__construct($db, $translator, self::NAME);
	}

    protected function getData($model, DateTime $start, DateTime $end)
    {
        $names = array();
        $dataSets = array();
        if ($model instanceof Model_Presence) {
            /** @var Model_Presence $model */
            $data = $model->getResponseData($start, $end);
            if ($data) {
                $key = Metric_ResponseTime::NAME;
                $names[$key] = $this->translate->trans('Metric_ResponseTime.title');
                $dataSets[$key] = $data;
            }
        } else if ($model instanceof Model_Country || $model instanceof Model_Group || $model instanceof Model_Region) {
            /** @var Model_Campaign $model */
            foreach ($model->getPresences() as $presence) {
                $data = $presence->getResponseData($start, $end);
                if ($data) {
                    $dataSets[$presence->getId()] = $data;
                    $names[$presence->getId()] = "[{$presence->getType()->getSign()}]" . $presence->getName();
                }
            }
        } else {
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
                $times = array();
                foreach ($dates as $date) {
                    $times[$date] = null;
                }
                foreach ($dataSet as $row) {
                    $datetime = $row->created;
                    $date = date('Y-m-d', strtotime($datetime));
                    if (empty($times[$date])) {
                        $times[$date] = round(floatval($row->diff), 2);
                    }
                }
                $times = array_values($times);
                array_unshift($times, $key);
                $columns[] = $times;
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