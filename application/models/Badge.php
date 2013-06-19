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
    public $score = null;                   //the total score of this bag (out of 100)
    public $ranking;                        //the rank of this badge
    public $rankingTotal;                   //the total number of presences/groups/countries
    public $class;                          //the model that this badge belongs to
    public $data;                           //the data for the badge
    public $badges;                         //the data for the badge
    public $item;                           //item this badge belongs to
    public $presences = array();            //array of presences (only one for Model_Presence badges)

    public function __construct($data, $type, $item, $class)
    {
        $this->type = $type;
        $this->title = ucfirst($type);
        $this->class = $class;
        $this->item = $item;

        if($this->class == 'Model_Presence'){
            $this->presences = array($item);
        } else {
            $this->presences = $item->getPresences();
        }

        if($type == 'total'){
            $this->badges = $data;
            $this->data = (object)array(
                'score' => $this->calculateTotalScores(),
                'rank' => array()
            );
        } else {
            $this->data = $data;
        }

        $this->rankingTotal = count($this->data->score);

        if(count($this->data->score) != ($this->data->rank)){
            $this->calculateRanking();
        }

        //if this item exists in the data->score array set the score, otherwise set to 0
        if(array_key_exists($item->id, $this->data->score)){
            $this->score = $this->data->score[$item->id];
        } else {
            $this->score = 0;
        }

        if(array_key_exists($item->id, $this->data->rank)){
            $this->ranking = $this->data->rank[$item->id];
        } else {
            $this->ranking = 0;
        }

    }

    private function calculateTotalScores()
    {
        $denominator = count($this->badges);
        $tempBadges = $this->badges;
        $tempBadge = array_pop ($tempBadges);

        $scores = $tempBadge->data->score;

        foreach($tempBadges as $badge){
            foreach($badge->data->score as $id => $score){

                $scores[$id] += $score;

            }
        }

        return array_map(function($a) use ($denominator) {
            return $a/$denominator;
        }, $scores);
    }

    private function calculateRanking()
    {
        //sort the scores
        arsort($this->data->score);

        $date = new DateTime();

        //get the ids for the ranks that we already have
        $ids = array_keys($this->data->rank);

        //set variables used in foreach below
        $ranking = 1;
        $lastScore = null;

        //set variable to be sent to insertData
        $setHistoryArgs = array();

        foreach($this->data->score as $id => $score) {

            //if score is not equal to last score increase ranking by 1
            if(is_numeric($lastScore) && $lastScore != $score){
                $ranking++;
            }

            //if we don't already have the rank for this
            if(!in_array($id, $ids)){

                if($this->class == 'Model_Presence' && $this->type != 'total'){
                    //add ranking to array of data to be inserted into db
                    $setHistoryArgs[] = array(
                        'presence_id' => $this->item->id,
                        'type' => $this->type . '_ranking',
                        'value' => $ranking,
                        'datetime' => $date->format('Y-m-d H-i-s')
                    );
                }

                //add ranking info to data->rank array
                $this->data->rank[$id] = $ranking;
            }

            //set current score to $lastScore for next value in array
            $lastScore = $score;

        }

        //when all is done, add new ranking data to db
        if(!empty($setHistoryArgs)) Model_Base::insertData('presence_history', $setHistoryArgs);
    }

}