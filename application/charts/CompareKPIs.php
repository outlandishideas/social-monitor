<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 14/10/2014
 * Time: 12:56
 */

abstract class Chart_CompareKPIs extends Chart_Abstract {

    protected $title = "Compare: KPIs";
    protected $description;
    protected $name = "chart_compare_kpis";

    public function getData(NewModel_Presence $presence, DateTime $start, DateTime $end)
    {
        //$data = $presence->getBadgeHistory();
        $data = array(
            array(
                "date" => "2014-09-10",
                "engagement" => 10,
                "quality" => 60,
                "reach" => 40
            ),
            array(
                "date" => "2014-09-11",
                "engagement" => 12,
                "quality" => 78,
                "reach" => 43
            ),
            array(
                "date" => "2014-09-12",
                "engagement" => 11,
                "quality" => 66,
                "reach" => 41
            ),
            array(
                "date" => "2014-09-13",
                "engagement" => 14,
                "quality" => 53,
                "reach" => 36
            ),
            array(
                "date" => "2014-09-14",
                "engagement" => 13,
                "quality" => 50,
                "reach" => 38
            ),
            array(
                "date" => "2014-09-15",
                "engagement" => 16,
                "quality" => 45,
                "reach" => 33
            )
        );

        if($data) {
            $points = array();
            foreach($data as $row){
            }
        }

    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
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
    public function getName()
    {
        return $this->name;
    }


}