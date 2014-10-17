<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

class Chart_Compare extends Chart_Abstract {

    protected static $title = "Compare: KPIs";
    protected static $description;
    protected static $name = "compare";

    protected $xLabel = "Time";
    protected $yLabel = "KPI Score";

    protected function getColumns($data = null)
    {
        $columns = array();
        $wantedColumns = array_merge($this->getDataColumns(), array($this->getXColumn()));

        foreach(array_keys((array)$data[0]) as $column){

            if(!in_array($column, $wantedColumns)) continue;

            $columns[] = array_reduce($data, function($carry, $row) use($column){
                $row = (array)$row;
                if(array_key_exists($column, $row)){
                    $carry[] = $row[$column];
                }
                return $carry;
            }, array($column));

        }
        return $columns;
    }

    protected function getNames($data = null)
    {
        $names = array();
        foreach(array_keys((array)$data[0]) as $column){
            if(in_array($column, $this->getDataColumns())){
                /** @var Badge_Abstract $badge */
                $badge = Badge_Factory::getBadge($column);
                $names[$column] = $badge->getTitle();
            }
        }
        return $names;
    }

    protected function getCampaignColumns($data = null)
    {
        //get the number of presences in this data so we can divide by this number later
        $presenceCount = count($this->getPresenceIdsFromData($data));

        $dataColumns = $this->getDataColumns();

        $reducedData = array_reduce($data, function($carry, $row) use ($dataColumns) {
            $row = (array)$row;
            //seed carry with empty array of columns names => 0
            if(!array_key_exists($row['date'], $carry)) {
                $carry[$row['date']] = array('date' => $row['date']);
                foreach($dataColumns as $colName){
                    $carry[$row['date']][$colName] = 0;
                }
            }
            //add the current rows data if colName exists
            foreach($dataColumns as $colName){
                if(array_key_exists($colName, $row) && is_numeric($row[$colName])){
                    $carry[$row['date']][$colName] += $row[$colName];
                }
            }
            return $carry;
        }, array());

        foreach($reducedData as &$row){
            foreach($dataColumns as $colName){
                $row[$colName] /= $presenceCount;
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
        return array(
            Badge_Quality::getName(),
            Badge_Engagement::getName(),
            Badge_Reach::getName()
        );
    }

    private function getPresenceIdsFromData($data)
    {
        return array_reduce($data, function($carry, $row){
            if(!in_array($row->presence_id, $carry)) $carry[] = $row->presence_id;
            return $carry;
        }, array());
    }


}