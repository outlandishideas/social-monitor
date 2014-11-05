<?php

abstract class Chart_Badge extends Chart_Compare {

    public function __construct(PDO $db = null)
    {
        parent::__construct($db);

        $this->xLabel = "Time";
    }

    /**
     * Gets the names of the presences, keyed by id
     * @param array $data
     * @return array
     */
    protected function getCampaignNames($data = null)
    {
        $presenceIds = $this->getPresenceIdsFromData($data);

        $names = array();
        if ($presenceIds) {
            $presences = Model_PresenceFactory::getPresencesById($presenceIds);

            foreach ($presences as $p) {
                $names[$p->getId()] = $p->getName();
            }
        }
        return $names;
    }

    private function getPresenceIdsFromData($data)
    {
        $ids = array_map(function($row){ return $row->presence_id; }, $data);
        $ids = array_unique($ids);
        return array_values($ids);
    }

    protected function getCampaignColumns($data = null)
    {
        //seed the $columns array
        $columns = array(
            $this->xColumn => array($this->xColumn)
        );
        foreach($this->getPresenceIdsFromData($data) as $presenceId){
            $columns[$presenceId] = array($presenceId);
        }

        $xCol = $this->xColumn;
        foreach($this->dataColumns as $column){
            $columns = array_reduce($data, function($carry, $row) use($column, $xCol){
                $row = (array)$row;
                //if we haven't already added the date to the date records, do so
                if(!in_array($row[$xCol], $carry[$xCol])) {
                    $carry[$xCol][] = $row[$xCol];
                }

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
            if(in_array($column[0], $this->dataColumns)){
                $value = $column[1];
                // foreach($range['range'] as $i => $score){
                //     if($value >= $score) {
                //         $color = $range['colors'][$i];
                //     }
                // }
                $color = Badge_Abstract::colorize($value);
                $colorValues[$column[0]] = $color;
            }
        }
        $data["colors"] = $colorValues;
        return $data;

    }

}