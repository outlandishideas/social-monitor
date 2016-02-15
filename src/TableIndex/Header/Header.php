<?php

namespace Outlandish\SocialMonitor\TableIndex\Header;

abstract class Header {

    const MODEL_TYPE_NONE = 'none';
    const MODEL_TYPE_PRESENCE = 'presence';
    const MODEL_TYPE_COUNTRY = 'country';
    const MODEL_TYPE_GROUP = 'group';
    const MODEL_TYPE_REGION = 'region';
    const MODEL_TYPE_CAMPAIGN = 'campaign';

    const SORT_TYPE_AUTO = 'auto';
    const SORT_TYPE_NONE = 'none';
    const SORT_TYPE_NUMERIC = 'numeric';
    const SORT_TYPE_NUMERIC_DATA_VALUE = 'data-value-numeric';
    const SORT_TYPE_NUMERIC_FUZZY = 'fuzzy-numeric';
    const SORT_TYPE_CHECKBOX = 'checkbox';

    const DISPLAY_TYPE_CSV = 'csv';
    const DISPLAY_TYPE_SCREEN = 'screen';
    const DISPLAY_TYPE_BOTH = 'both';

    const NO_VALUE = 'N/A';

    protected static $name;

    protected $label;
    protected $width;
    protected $description;
    protected $sort = self::SORT_TYPE_AUTO;
    protected $display = self::DISPLAY_TYPE_BOTH;
    protected $allowedTypes = array(self::MODEL_TYPE_NONE);
    protected $cellClasses = array();

    /**
     * produces the <th> element for the header row of a table
     *
     * @return mixed
     */
    public function getTableHeaderElement(){
        $properties = array(
            "data-name" => $this->getName(),
            "data-sort" => $this->getSort()
        );
        if($this->getDescription() !== null) {
            $properties['title'] = $this->getDescription();
        }
        if($this->getWidth() !== null) {
            $properties['data-width'] =  $this->getWidth();
        }

        $html = "<th";
        foreach($properties as $property => $value){
            $html .= " {$property}='{$value}'";
        }
        $html .= ">{$this->getLabel()}</th>";
        return $html;
    }

    /**
     * Gets the HTML rendering of the value for this column
     * @param $model
     * @return mixed
     */
    public function getTableCellValue($model) {
        return $this->getFormattedValue($model);
    }

    /**
     * @return mixed
     */
    public static function getName()
    {
        return static::$name;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return mixed
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return mixed
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return string
     */
    public function getDisplayType()
    {
        return $this->display;
    }

    public function isForCsv() {
        return $this->display == self::DISPLAY_TYPE_BOTH || $this->display == self::DISPLAY_TYPE_CSV;
    }

    public function isForScreen() {
        return $this->display == self::DISPLAY_TYPE_BOTH || $this->display == self::DISPLAY_TYPE_SCREEN;
    }

    public function isAllowedType($type)
    {
        return in_array($type, $this->allowedTypes);
    }

    /**
     * @return string[]
     */
    public function getAllowedTypes()
    {
        return $this->allowedTypes;
    }

    /**
     * @return string[]
     */
    public function getCellClasses()
    {
        return array_merge(array('cell-' . self::getName()), $this->cellClasses);
    }

    function getValue($model = null)
    {
        return $model;
    }

    final function getFormattedValue($model = null)
    {
        return $this->formatValue($this->getValue($model));
    }

    function formatValue($value) {
        return $value;
    }
}