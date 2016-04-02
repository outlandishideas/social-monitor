<?php

use Outlandish\SocialMonitor\Database\Database;

class Chart_Compare extends Chart_Abstract {

    const NAME = "compare";

    protected $dataColumns;
    protected $xColumn;

    public function __construct(Database $db, $translator, $name = null)
    {
        parent::__construct($db, $translator, $name ?: self::NAME);

        $this->xColumn = 'date';
        $this->dataColumns = array(
            Badge_Quality::NAME,
            Badge_Engagement::NAME,
            Badge_Reach::NAME
        );
    }

    protected function getColumns($data = null)
    {
        $columns = array();
        $wantedColumns = array_merge($this->dataColumns, array($this->xColumn));

        foreach ($wantedColumns as $column) {
            $dataRow = array($column);
            if ($data) {
                foreach ($data as $row) {
                    $row = (object)$row;
                    $value = isset($row->$column) ? $row->$column : null;
                    if ($column != $this->xColumn && !is_null($value)) {
                        $value = round($value, 1);
                    }
                    $dataRow[] = $value;
                }
            }
            $columns[] = $dataRow;
        }
        return $columns;
    }

    protected function getNames()
    {
        $names = array();
        foreach($this->dataColumns as $column){
            /** @var Badge_Abstract $badge */
            $badge = Badge_Factory::getBadge($column);
            $names[$column] = $badge->getTitle();
        }
        return $names;
    }

    protected function getCampaignColumns($data = null, $property = 'presence_id')
    {
        if (!is_array($data)) {
            return array();
        }

        //get the number of presences in this data so we can divide by this number later
        $presenceCount = count($this->getPropertyFromData($property, $data));

        $dataColumns = $this->dataColumns;

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
        return $this->getNames();
    }

    protected function getData($model, DateTime $start, DateTime $end)
    {
        if ($model instanceof Model_Presence) {
            /** @var Model_Presence $model */
            $data = $model->getBadgeHistory($start, $end);
            $columns = $this->getColumns($data);
            $names = $this->getNames();
        } else if ($model instanceof Model_Region) {
            $data = $model->getBadgeHistory($start, $end);
            $columns = array_values($this->getCampaignColumns($data, 'campaign_id'));
            if (get_class($this) == 'Chart_Compare') {
                $names = $this->getNames();
            } else {
                $names = array();
                foreach (Model_Country::fetchByIds($this->getCountryIdsFromData($data)) as $c) {
                    /** @var $c Model_Country */
                    $names[$c->id] = $c->getName();
                }
            }
        } else if ($model instanceof Model_Country || $model instanceof Model_Group) {
            /** @var Model_Campaign $model */
            $data = $model->getBadgeHistory($start, $end);
            $columns = array_values($this->getCampaignColumns($data));
            $names = $this->getCampaignNames($data);
        } else {
            return array();
        }


        return array(
            "x" => $this->xColumn,
            "columns" => $columns,
            "names" => $names
        );

    }

    public function getYAxis()
    {
        return array(
            "label" => $this->yLabel,
            "max" => 100,
            "min" => 0,
            "position" => 'outer-middle',
            "padding" => array(
                "top" =>  0,
                "bottom" => 0
            )
        );
    }

    protected function getPresenceIdsFromData($data)
    {
        return $this->getPropertyFromData('presence_id', $data);
    }

    protected function getCountryIdsFromData($data)
    {
        return $this->getPropertyFromData('campaign_id', $data);
    }

    protected function getPropertyFromData($property, $data)
    {
        $values = array_map(function($row) use ($property) { return $row->$property; }, $data);
        $values = array_unique($values);
        return array_values($values);
    }
}