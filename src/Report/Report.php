<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 07/05/2015
 * Time: 14:16
 */

namespace Outlandish\SocialMonitor\Report;


use Badge_Engagement;
use Badge_Factory;
use Badge_Quality;
use Badge_Reach;
use Badge_Total;
use DateTime;
use Outlandish\SocialMonitor\Query\BadgeRankDataQuery;

class Report {

    /**
     * @var Reportable
     */
    private $model;
    /**
     * @var DateTime
     */
    private $start;
    /**
     * @var DateTime
     */
    private $end;
    private $ranks;
    private $charts;
    /**
     * @var BadgeRankDataQuery
     */
    private $query;

    public function __construct(BadgeRankDataQuery $query, Reportable $model, DateTime $start, DateTime $end)
    {
        $this->model = $model;
        $this->start = $start;
        $this->end = $end;
        $this->query = $query;
    }

    public function generate()
    {
        $this->ranks = $this->getRankData();
        $this->charts = $this->getChartData();
    }

    public function getType()
    {
        return $this->model->getType();
    }

    public function getName()
    {
        return $this->model->getName();
    }

    public function getIcon()
    {
        return $this->model->getIcon();
    }

    public function getDateRange()
    {
        return $this->start->format('j M Y') . " - " . $this->end->format('j M Y');
    }

    public function getRanks()
    {
        return $this->ranks;
    }

    public function getRankData()
    {
        $ranks = [];
        $orderedRanks = [
            Badge_Total::NAME,
            Badge_Reach::NAME,
            Badge_Engagement::NAME,
            Badge_Quality::NAME
        ];

        foreach (Badge_Factory::getBadges() as $badge) {
            $score = $this->getBadgeRank($badge);
            $oldScore = $this->getPreviousBadgeRank($badge);
            $change = $score !== null ? ($score - $oldScore) : "";
            $ranks[$badge->getName()] = [
                'title' => "{$badge->getTitle()} Rank",
                'rank' => $score !== null ? $score : "N/A",
                'denominator' => $this->getRankDenominator(),
                'ordinal' => $score !== null ? $this->getOrdinalIndicator($score) : "",
                'chart_class' => $this->getChartClass($badge),
                'change' => [
                    'number' => $change,
                    'class' => $change > 0 ? 'positive' : 'negative'
                ]
            ];
        }

        return array_merge(array_flip($orderedRanks), $ranks);
    }

    private function getBadgeRank($badge)
    {
        return $this->query->get($this->model, $badge, $this->end);
    }

    private function getPreviousBadgeRank($badge)
    {
        return $this->query->get($this->model, $badge, $this->start);
    }

    private function getRankDenominator()
    {
        return $this->model->numberOfType();
    }

    private function getOrdinalIndicator($number) {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return 'th';
        else
            return $ends[$number % 10];
    }

    public function getChartData()
    {
        $data = [];
        foreach (Badge_Factory::getBadges() as $badge) {
            $chart = [
                "data" => [
                    "x"=> 'x',
                    "columns" => $this->getChartColumn($badge),
                    "types" => [
                        "{$badge->getTitle()}" => 'area-spline'
                    ]
                ],
                "axis" => [
                    "x" => [
                        "type" => 'timeseries',
                        "tick" => [
                            "format" => "%e %b %y",
                            "count" => "6"
                        ]
                    ],
                    "y" => [
                        "max" => 100,
                        "min" => 0,
                        "tick" => ["count" => 3],
                        "padding" => ["top" => 0, "bottom" => 0]
                    ]
                ],
                "grid" => ["y" => ["show" => true]],
                "point" => ["show" => false],
                "legend" => ["show" => false],
                "padding" => ["left" => 100],
                "size" => [
                    "width" => 640,
                    "height" => 120
                ],
                "bindto" => "." . $this->getChartClass($badge)
            ];
            $data[] = json_encode($chart);
        }

        return $data;
    }

    public function getCharts()
    {
        return $this->charts;
    }

    private function getChartClass(\Badge_Abstract $badge)
    {
        return "{$badge->getName()}_chart";
    }

    private function getChartColumn(\Badge_Abstract $badge)
    {
        $chartData =  $this->query->getChartData($this->model, $badge, $this->start, $this->end);
        $keys = array_keys($chartData);
        $values = array_values($chartData);
        array_unshift($keys, 'x');
        array_unshift($values, $badge->getTitle());
        return [
            $keys,$values
        ];
    }
}