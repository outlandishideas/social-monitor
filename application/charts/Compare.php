<?php

class Chart_Compare extends Chart_Abstract {

    protected static $title = "Compare: KPIs";
    protected static $name = "compare";

    protected $dataColumns;

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);
        $this->xLabel = "Time";
        $this->yLabel = "KPI Score";
        $this->dataColumns = array(
            Badge_Quality::getName(),
            Badge_Engagement::getName(),
            Badge_Reach::getName()
        );
    }

    protected function getColumns($data = null)
    {
        $columns = array();
        $wantedColumns = array_merge($this->getDataColumns(), array($this->getXColumn()));

        foreach ($wantedColumns as $column) {
            $dataRow = array($column);
            foreach ($data as $row) {
                $row = (object)$row;
                $value = isset($row->$column) ? $row->$column : null;
                if ($column != $this->getXColumn()) {
                    $value = round($value, 1);
                }
                $dataRow[] = $value;
            }
            $columns[] = $dataRow;
        }
        return $columns;
    }

    protected function getNames()
    {
        $names = array();
        foreach($this->getDataColumns() as $column){
            /** @var Badge_Abstract $badge */
            $badge = Badge_Factory::getBadge($column);
            $names[$column] = $badge->getTitle();
        }
        return $names;
    }

    protected function getCampaignColumns($data = null)
    {
        if (!is_array($data)) {
            return array();
        }

        //get the number of presences in this data so we can divide by this number later
        $presenceCount = count($this->getPresenceIdsFromData($data));

        $dataColumns = $this->getDataColumns();

        $reducedData = array();
        foreach ($data as $row) {
            $date = $row->date;
            //seed carry with empty array of columns names => 0
            if(!array_key_exists($date, $reducedData)) {
                $dateObj = new stdClass();
                $dateObj->date = $date;
                foreach($dataColumns as $colName){
                    $dateObj->$colName = 0;
                }
                $reducedData[$date] = $dateObj;
            }
            //add the current rows data if colName exists
            foreach($dataColumns as $colName){
                if(isset($row->$colName) && is_numeric($row->$colName)){
                    $reducedData[$date]->$colName += $row->$colName;
                }
            }
        }

        foreach($reducedData as &$row){
            foreach($dataColumns as $colName){
                $row->$colName = round($row->$colName/$presenceCount, 1);
            }
        }

        return $this->getColumns(array_values($reducedData));
    }

    protected function getCampaignNames($data = null)
    {
        return $this->getNames($data);
    }

    protected function getData($model, DateTime $start, DateTime $end)
    {
        switch(get_class($model)) {
            case "NewModel_Presence":
                /** @var NewModel_Presence $model */
                $data = $model->getBadgeHistory($start, $end);
                $columns = $this->getColumns($data);
                $names = $this->getNames($data);
                break;
            case "Model_Country":
            case "Model_Group":
            case "Model_Region":
                /** @var Model_Campaign $model */
                $data = $model->getBadgeHistory($start, $end);
                $columns = array_values($this->getCampaignColumns($data));
                $names = $this->getCampaignNames($data);
                break;
            default:
                return array();
        }


        return array(
            "x" => $this->getXColumn(),
            "columns" => $columns,
            "names" => $names
        );

    }

    public function getXAxis()
    {
        return array(
            "type" => 'timeseries',
            "label" => $this->getXLabel(),
            "position" => 'outer-center'
        );
    }

    public function getYAxis()
    {
        return array(
            "label" => $this->getYLabel(),
            "max" => 100,
            "min" => 0,
            "position" => 'outer-middle',
            "padding" => array(
                "top" =>  0,
                "bottom" => 0
            )
        );
    }

    public function getXColumn()
    {
        return "date";
    }

    public function getDataColumns()
    {
        return $this->dataColumns;
    }

    private function getPresenceIdsFromData($data)
    {
        $ids = array();
        if (is_array($data)) {
            $ids = array_map(function($a) { return $a->presence_id; }, $data);
            $ids = array_unique($ids);
            $ids = array_values($ids);
        }
        return $ids;
    }


}