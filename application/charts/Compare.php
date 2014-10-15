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

    public function getData(NewModel_Presence $presence, DateTime $start, DateTime $end)
    {
        $stuff = $presence->getBadgeHistory($start, $end);

        $wantedColumns = array_merge(Badge_Factory::getBadgeNames(), array('date'));

        if($stuff) {
            $columns = array();
            $names = array();
            foreach(array_keys((array)$stuff[0]) as $column){
                if(!in_array($column, $wantedColumns)) continue;
                $columns[] = array_reduce($stuff, function($carry, $row) use($column){
                    $row = (array)$row;
                    if(array_key_exists($column, $row)){
                        $carry[] = $row[$column];
                    }
                    return $carry;
                }, array($column));
                if(in_array($column, Badge_Factory::getBadgeNames())){
                    /** @var Badge_Abstract $badge */
                    $badge = Badge_Factory::getBadge($column);
                    $names[$column] = $badge->getTitle();
                }
            }

            $data = array(
                "x" => 'date',
                "columns" => $columns,
                "names" => $names
            );

            $axis = array(
                "x" =>  array(
                    "type" => 'timeseries',
                    "label" => $this->getXLabel(),
                    "position" => 'outer-center'
                ),
                "y" => array(
                    "label" => $this->getYLabel(),
                    "max" => 100,
                    "min" => 0,
                    "position" => 'outer-middle',
                    "padding" => array(
                        "top" =>  0,
                        "bottom" => 0
                    )
                )
            );

            $return = array(
                "bindto" => '#new-chart',
                "data" => $data,
                "axis" => $axis
            );
        }
        return isset($return) ? $return : null;

    }


}