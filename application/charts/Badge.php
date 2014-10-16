<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

abstract class Chart_Badge extends Chart_Compare {

    protected static $title;
    protected static $description;
    protected static $name;

    protected $xLabel = "Time";
    protected $yLabel;

    protected function getData(NewModel_Presence $presence, DateTime $start, DateTime $end)
    {

        $data = parent::getData($presence, $start, $end);
        $data["type"] = "area-spline";

        $colors = array(
            'grey' => '#d2d2d2',
            'red' => '#D06959',
            'green' => '#84af5b',
            'orange' => '#F1DC63',
            'yellow' => '#FFFF50'
        );

        $range = array(
            'range' => array(0, 1, 20, 50, 80, 100),
            'colors' => array($colors['grey'], $colors['red'],$colors['red'], $colors['yellow'], $colors['green'], $colors['green'])
        );

        $colorValues = array();
        $color = $range['colors'][0];

        foreach($data['columns'] as $column) {
            if(in_array($column[0], $this->getDataColumns())){
                $value = $column[1];
                foreach($range['range'] as $i => $score){
                    if($value >= $score) $color = $range['colors'][$i];
                }
                $colorValues[$column[0]] = $color;
            }
        }
        $data["colors"] = $colorValues;
        return $data;

    }

}