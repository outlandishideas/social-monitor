<?php

class Model_Presence extends Model_Base {

    public $type;
    public $title;
    public $score = 0;
    public $ranking;
    public $rankingTotal;
    public $presences = array();
    public $kpis = array();
    public $start;
    public $end;

    public function __construct($type,$title,$presences = array(), $startDate = null, $endDate = null)
    {
        $this->type = $type;
        $this->title = $title;
        $this->presences = $presences;

        $this->setKPIS();

        $this->setDate($startDate, $endDate);

        $this->populateData();
    }

    /**
     * populate the data for this badge by going through each of the presences and add things all together
     */
    private function populateData()
    {
        foreach($this->presences as $presence){
            foreach(array_keys($this->kpis) as $metric){

                $this->calculateMetric($metric,$presence);

            }
        }

        $this->calculateScores();

        $this->divideByPresences();
    }

    /**
     *
     */
    private function calculateMetrics($metric, $presence)
    {
        switch($metric){

            case(self::METRIC_POSTS_PER_DAY):

                $target = BaseController::getOption('updates_per_day');
                $actual = $presence->getAveragePostsPerDay($this->start, $this->end);

                if($actual > $target){
                    $percent = 100;
                } else {
                    $percent = ( $actual / $target ) * 100;
                }

                $this->kpis[$metric]->title = 'Average Posts Per Day';
                $this->kpis[$metric]->target = $target;
                $this->kpis[$metric]->actual = $actual;
                $this->kpis[$metric]->percent += $percent;

                break;

            case(self::METRIC_LINKS_PER_DAY):

                $target = BaseController::getOption('updates_per_day');
                $actual = $presence->getAverageLinksPerDay($this->start, $this->end);

                if($actual > $target){
                    $percent = 100;
                } else {
                    $percent = ( $actual / $target ) * 100;
                }

                $this->kpis[$metric]->title = 'Average Links Per Day';
                $this->kpis[$metric]->target = $target;
                $this->kpis[$metric]->actual = $actual;
                $this->kpis[$metric]->percent += $percent;

                break;

            case(self::METRIC_LIKES_PER_POST):

                $target = BaseController::getOption('updates_per_day');
                $actual = $presence->getAverageLikesPerPost($this->start, $this->end);

                if($actual > $target){
                    $percent = 100;
                } else {
                    $percent = ( $actual / $target ) * 100;
                }

                $this->kpis[$metric]->title = 'Average Likes Per Post';
                $this->kpis[$metric]->target = $target;
                $this->kpis[$metric]->actual = $actual;
                $this->kpis[$metric]->percent += $percent;

                break;

            case(self::METRIC_RESPONSE_TIME):

                $target = BaseController::getOption('updates_per_day');
                $actual = $presence->getAverageResponseTime($this->start, $this->end);

                if($actual > $target){
                    $percent = ( $target / $actual ) * 100;
                } else {
                    $percent = 100;
                }

                $this->kpis[$metric]->title = 'Average Response Time';
                $this->kpis[$metric]->target = $target;
                $this->kpis[$metric]->actual = $actual;
                $this->kpis[$metric]->percent += $percent;

                break;

            case(self::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS):

                $target = BaseController::getOption('updates_per_day');
                $actual = $presence->getRatioRepliesToOthersPosts($this->start, $this->end);

                if($actual > $target){
                    $percent = 100;
                } else {
                    $percent = ( $actual / $target ) * 100;
                }

                $this->kpis[$metric]->title = 'Ratio of Replies to Posts from others';
                $this->kpis[$metric]->target = $target;
                $this->kpis[$metric]->actual = $actual;
                $this->kpis[$metric]->percent += $percent;

                break;

            default:

                $this->kpis[$metric]->title = 'Default';
                $this->kpis[$metric]->target = 0;
                $this->kpis[$metric]->actual = 0;
                $this->kpis[$metric]->percent += 0;

        }
    }

    /**
     * If presences > 1 divide the scores by count($presences)
     */
    private function divideByPresences()
    {
        $denominator = count($this->presences);

        if($denominator > 1){

            $this->score /= $denominator;

            if(!empty($this->kpis)) {
                foreach($this->kpis as $kpi) {

                    $kpi->score /= $denominator;
                    unset($this->target);
                    unset($this->actual);

                }
            }

        }
    }

    private function setDate($start, $end)
    {
        if(!$start || !$end){

            $endDate = new DateTime();
            $startDate = new DateTime();
            $startDate->sub(DateInterval::createFromDateString('1 month'));

            $end = $endDate->format('Y-m-d');
            $start = $startDate->format('Y-m-d');

        }

        $this->start =  $start;
        $this->end = $end;
    }

    private function setKPIS()
    {
        if($this->type != 'total'){
            $allBadges = Model_Presence::ALL_BADGES();
            $kpis = $allBadges[$this->type];
            foreach($kpis as $kpi){
                $this->kpis[$kpi] = (object)array(
                    'target' => 0,
                    'actual' => 0,
                    'score' => 0,
                    'type' => $kpi
                );
            }
        }
    }

}