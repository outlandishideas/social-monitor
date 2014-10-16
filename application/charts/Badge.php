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

    protected function getCampaignNames($data = null)
    {
        $names = array();

        $presenceIds = $this->getPresenceIdsFromData($data);

        $presences = NewModel_PresenceFactory::getPresencesById($presenceIds);

        return array_reduce($presences, function($carry, $presence){
            /** @var NewModel_Presence $presence */
            $carry[$presence->getId()] = $presence->getName();
            return $carry;
        }, $names);
    }

    private function getPresenceIdsFromData($data)
    {
        return array_reduce($data, function($carry, $row){
            if(!in_array($row->presence_id, $carry)) $carry[] = $row->presence_id;
            return $carry;
        }, array());
    }

    protected function getCampaignColumns($data = null)
    {
        //seed the $columns array
        $columns = array(
            $this->getXColumn() => array($this->getXColumn())
        );
        foreach($this->getPresenceIdsFromData($data) as $presenceId){
            $columns[$presenceId] = array($presenceId);
        }

        $xCol = $this->getXColumn();
        foreach($this->getDataColumns() as $column){
            $columns = array_reduce($data, function($carry, $row) use($column, $xCol){
                $row = (array)$row;
                //if we haven't already added the date to the date records, do so
                if(!in_array($row[$xCol], $carry[$xCol])) $carry[$xCol][] = $row[$xCol];

                //our data column appears in this row, add it to the correct presence
                if(array_key_exists($column, $row)){
                    $carry[$row['presence_id']][] = $row[$column];
                }
                return $carry;
            }, $columns);
        }
        return $columns;
    }

    protected function getData($model, DateTime $start, DateTime $end)
    {

        $data = parent::getData($model, $start, $end);
        $data["type"] = "spline";

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