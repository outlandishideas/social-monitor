<?php

/**
 * Class Model_Badge
 * @param array $presences - the presences to be included when calculating the metrics of a badge (multiple presences mean either a group or country)
 * @param string $type - the type of badge this is (Total, Reach, Engagement, Quality)
 * @param string $model - the Model that this badge is for
 * @param int $rankingTotal - the total number of items for this Model type (include in construct to only count once for all four badges)
 * @param boolean $metrics - if true, populate badge with metric data. If false, try and get score from database first
 * @param string $start - the start date for db queries (format Y-m-d)
 * @param string $end - the end date for db queries (format Y-m-d)
 */
class Model_Badge {

    public $type;                           //the badge type
    public $title;                          //the title for this badge ( ucfirst($type) )
    public $score = null;                      //the total score of this bag (out of 100)
    public $ranking;                        //the rank of this badge
    public $rankingTotal;                   //the total number of presences/groups/countries
    public $presences = array();            //the array of presences
    public $badges = array();               //the array of badges (**Total Badge only**)
    public $kpis = array();                 //the array of metrics
    public $start;                          //the start date for db queries
    public $end;                            //the end date for db queries
    public $model;                          //the model that this badge belongs to
    public $thisItem;                       //only used if ranking is calculated, captures the object of the item this badge belongs to

    public function __construct($elements = array(), $type, $model, $rankingTotal, $metrics = true, $start = null, $end = null)
    {
        $this->type = $type;
        $this->title = ucfirst($type);
        $this->model = $model;
        $this->rankingTotal = $rankingTotal;

        //set the start and end dates as properties
        $this->setDate($start, $end);

        //make sure that $elements is an array
        if(!is_array($elements)) $elements = array($elements);

        //if this is a Total Badge, $elements are badge objects not presences
        if($this->type == 'total'){

            $this->badges = $elements;

            //get presences for this badge by taking element from $this->badges and adding the $presences from that object to $this
            $badge = end($elements);
            $this->presences = $badge->presences;

        } else {

            //set the KPIs for this badge
            $this->setKPIS();

            $this->presences = $elements;

        }

        //if we don't want to get the metrics just get the score
        //only works if badge is for presence (we don't store group or country data)
        if (!$metrics && $this->model == 'Model_Presence') {

            $this->getScore();

        }

        //if we want to get the metrics or if getting just the score was not possible
        // populate badge data based on its type (total or not)
        if ($metrics || $this->score === null) {

            if($this->type == 'total') {
                $this->populateTotalData();
            } else {
                $this->populateBadgeData();
            }

        }

    }

    public function getRanking($id)
    {
        $class = $this->model;
        $this->thisItem = $class::fetchById($id);

        //if presence check historyData first
        if($class == 'Model_Presence'){

            $date = $this->historyDataDate();

            //check to see if history Data exists
            $functionName = 'get'.ucfirst($this->type).'RankingData';
            $rows = $this->thisItem->$functionName($date->start, $date->end);

            if($rows){

                $row = array_pop($rows);
                $this->ranking = $row->value;
                return;

            }

        }

        //if no results yet, calculate the score
        $scores = array();

        $Items = $class::fetchAll();
        foreach($Items as $item){

            //if we are ranking a Total badge, we need to get all the other badge scores
            //thankfully we should always be getting scores for the other badges from the database at this point
            if($this->type == 'total') {

                $elements = array();
                foreach(Model_Presence::ALL_BADGES() as $badge => $array){
                    $elements[$badge] = new Model_Badge($this->presences, $badge, $class, 0, false, $this->start, $this->end);
                }

            //if it is a normal badge we will instantiate an object for each presence and generate a score for each
            } else {

                $elements = $this->model == 'Model_Presence' ? array($item) : $item->getPresences() ;

            }

            $itemBadge = new Model_Badge($elements, $this->type, $class, 0, false, $this->start, $this->end);

            $scores[] = (object)array(
                'id' => $item->id,
                'badge' => $itemBadge
            );
        }

        //sort the scores array by the score of each presence
        usort($scores, function($a, $b){
            if($a->badge->score == $b->badge->score) return 0;
            return ($a->badge->score < $b->badge->score) ? 1 : -1 ;
        });

        //go through each score to determine the ranking of the presence in question
        $ranking = 0;
        for($i=0;$i<count($scores);$i++){

            //if its the first score, set the ranking to 1 (for 1st)
            //else if the score does not match the previous score increase the ranking
            if($i == 0) {
                $ranking++;
            } else {
                if($scores[$i]->badge->score != $scores[$i-1]->badge->score){
                    $ranking++;
                }
            }

            //if the current id matches this presences id break out of the loop and add the current ranking as this badges ranking
            if($scores[$i]->id == $id){
                $this->ranking = $ranking;
                if($class = 'Model_Presence') $this->setRanking();
                return;
            }
        }

        $this->ranking = 'n/a';
        return;

    }

    private function setRanking()
    {
        $date = new DateTime();

        //put together a package of data to be sent to the insertData function
        $data = array(array(
            'value'=>$this->ranking,
            'type'=>$this->type.'_ranking',
            'presence_id'=>$this->thisItem->id,
            'datetime'=>$date->format('Y-m-d H:i:s')
        ));

        return Model_Presence::insertData('presence_history',$data);
    }

    private function setScore($score, $presence)
    {
        $date = new DateTime();

        //put together a package of data to be sent to the insertData function
        $data = array(array(
            'value'=>$score,
            'type'=>$this->type,
            'presence_id'=>$presence->id,
            'datetime'=>$date->format('Y-m-d H:i:s')
        ));

        return Model_Presence::insertData('presence_history',$data);
    }

    /**
     * Try and get the score from the database
     */
    private function getScore()
    {
        foreach($this->presences as $presence){

            $date = $this->historyDataDate();

            //check to see if history Data exists
            $functionName = 'get'.ucfirst($this->type).'Data';
            $rows = $presence->$functionName($this->type, $date->start, $date->end);

            if(!empty($rows)){
                $row =  array_pop($rows);
                $this->score += $row->value;
            } else {

                if($this->type == 'total'){

                    $score = 0;
                    foreach($this->badges as $badge){

                        $score += $badge->score;

                    }

                    $score /= count($this->badges);
                    $this->score += $score;


                } else {

                    $score = $this->calculateMetrics($presence);
                    $this->setScore($score, $presence);
                    $this->score += $score;

                }

            }

        }

        $this->score /= count($this->presences);
    }



    /**
     * populate the data for this badge by going through each of the presences and add things all together
     * @return int
     */
    private function populateBadgeData()
    {
        if(empty($this->presences)){

            return;

        } else {

            foreach($this->presences as $presence){
                $this->calculateMetrics($presence);
            }

            $score = 0;
            foreach($this->kpis as $kpi){

                $kpi->score /= count($this->presences);
                $score += $kpi->score;

            }


            $score /= count($this->kpis);
            $this->score = $score;
        }
    }

    /**
     * populate the data for the total badge by going through each of the badges and add the scores all together
     */
    private function populateTotalData()
    {
        foreach($this->badges as $badge){

            $this->score += $badge->score;

        }

        $this->score /= count($this->badges);
    }

    /**
     * calculate the metrics and total badge score for a presence. Returns the total badge score
     * @param object $presence
     * @return int
     */
    private function calculateMetrics($presence)
    {
        $score = 0;
        foreach(array_keys($this->kpis) as $metric){

            switch($metric){

                case(Model_Presence::METRIC_POSTS_PER_DAY):

                    $target = BaseController::getOption('updates_per_day');
                    $actual = $presence->getAveragePostsPerDay($this->start, $this->end);

                    if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Average Posts Per Day';

                    break;

                case(Model_Presence::METRIC_LINKS_PER_DAY):

                    $target = BaseController::getOption('updates_per_day');
                    $actual = $presence->getAverageLinksPerDay($this->start, $this->end);

                    if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Average Links Per Day';

                    break;

                case(Model_Presence::METRIC_LIKES_PER_POST):

                    $target = BaseController::getOption('updates_per_day');
                    $actual = $presence->getAverageLikesPerPost($this->start, $this->end);

                    if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Average Likes Per Post';

                    break;

                case(Model_Presence::METRIC_RESPONSE_TIME):

                    $target = BaseController::getOption('updates_per_day');
                    $actual = $presence->getAverageResponseTime($this->start, $this->end);

                    if($actual > $target){
                        $percent = ( $target / $actual ) * 100;
                    } else {
                        $percent = 100;
                    }

                    $title = 'Average Response Time';

                    break;

                case(Model_Presence::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS):

                    $target = BaseController::getOption('updates_per_day');
                    $actual = $presence->getRatioRepliesToOthersPosts($this->start, $this->end);

                    if($actual > $target){
                        $percent = 100;
                    } else {
                        $percent = ( $actual / $target ) * 100;
                    }

                    $title = 'Ratio of Replies to Posts from others';

                    break;

                default:

                    $title = 'Default';
                    $target = 0;
                    $actual = 0;
                    $percent = 0;

            }

            if(!empty($this->kpis)){
                $handle = $presence->handle;

                $this->kpis[$metric]->target = $target;
                $this->kpis[$metric]->actual = $actual;
                $this->kpis[$metric]->score += $percent;
                $this->kpis[$metric]->title = $title;
            }
            $score += $percent;

        }

        $score /= count($this->kpis);

        return $score;
    }

    private function setDate($start = null, $end = null)
    {
        if( !$start || !$end ){

            $endDate = new DateTime();
            $startDate = new DateTime();
            $startDate->sub(DateInterval::createFromDateString('1 month'));

            $end = $endDate->format('Y-m-d');
            $start = $startDate->format('Y-m-d');

        }

        $this->start =  $start;
        $this->end = $end;
    }

    private function historyDataDate()
    {
        //create start and end dates for db query
        $date = new DateTime();
        $startDate = $date->format('Y-m-d');
        $endDate = $startDate . ' 23:59:59';
        $startDate = $startDate . ' 00:00:00';

        return (object)array(
            'start' => $startDate,
            'end' => $endDate
        );
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