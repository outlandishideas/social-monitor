<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 16/10/2014
 * Time: 15:37
 */

abstract class Header_Abstract {

    protected static $name;
    protected $label;
    protected $width;
    protected $description;
    protected $sort = "auto";
    protected $csv = true;

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
        if($this->getDescription() !== null) $properties['title'] = $this->getDescription();
        if($this->getWidth() !== null) $properties['data-widht'] =  $this->getWidth();

        $html = "<th ";
        foreach($properties as $property => $value){
            $html .= "{$property}='{$value}'";
        }
        $html .= ">{$this->getLabel()}</th>";
        return $html;
    }

    abstract public function getTableCellValue($model);

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
     * @return mixed
     */
    public function getCsv()
    {
        return $this->csv;
    }

}