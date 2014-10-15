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

    protected function getData(NewModel_Presence $presence, DateTime $start, DateTime $end)
    {
        $data = $presence->getBadgeHistory($start, $end);

        return array(
            "x" => $this->getXColumn(),
            "columns" => $this->getColumns($data),
            "names" => $this->getNames($data)
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


}