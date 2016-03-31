<?php

class Chart_Popularity extends Chart_Abstract {

    const NAME = "popularity";

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
            $data = $model->getPopularityData($start, $end);
            if ($data) {
                $key = Metric_Popularity::NAME;
                $names[$key] = $this->translate->trans('metric.' . $key . '.title');
                $dataSets[$key] = $data;
            }
        } else if ($model instanceof Model_Country || $model instanceof Model_Group || $model instanceof Model_Region) {
            /** @var Model_Campaign $model */
            foreach ($model->getPresences() as $presence) {
                $data = $presence->getPopularityData($start, $end);
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