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
                $names[$p->getId()] = "[{$p->getType()->getSign()}]" . $p->getName();
            }
        }
        return $names;
    }

    protected function getCampaignColumns($data = null, $property = 'presence_id')
    {
        //seed the $columns array
        $columns = array(
            $this->xColumn => array($this->xColumn)
        );
        foreach($this->getPropertyFromData($property, $data) as $id){
            $columns[$id] = array($id);
        }

        $xCol = $this->xColumn;
        foreach($this->dataColumns as $column){
            $columns = array_reduce($data, function($carry, $row) use($column, $xCol, $property){
                $row = (array)$row;
                //if we haven't already added the date to the date records, do so
                if(!in_array($row[$xCol], $carry[$xCol])) {
                    $carry[$xCol][] = $row[$xCol];
                }

                //our data column appears in this row, add it to the correct presence
                if(array_key_exists($column, $row)){
                    $carry[$row[$property]][] = $row[$column];
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

        $colorValues = array();

        foreach($data['columns'] as $column) {
            if(in_array($column[0], $this->dataColumns)){
                $value = $column[1];
                $color = Badge_Abstract::colorize($value);
                $colorValues[$column[0]] = $color;
            }
        }
        $data["colors"] = $colorValues;
        return $data;

    }

}