<?php

/**
 * Class Model_Badge
 * @param array $data - the data to calculate this badge from
 * @param string $type - the type of badge this is (Total, Reach, Engagement, Quality)
 * @param string $item - the item (Model_Presence, Model_Campaign) tis badge is for
 * @param int $class - The class that this item belongs to
 */
class Model_Badge {

    public $type;                           //the badge type
    public $title;                          //the title for this badge ( ucfirst($type) )
    public $score = null;                   //the total score of this bag (out of 100)
    public $rank;                        //the rank of this badge
    public $rankTotal;                   //the total number of presences/groups/countries
    public $class;                          //the model that this badge belongs to
    public $data;                           //the data for the badge
    public $badges;                         //the data for the badge
    public $item;                           //item this badge belongs to
    public $presences = array();            //array of presences (only one for Model_Presence badges)
    public $metrics = array();

    //Badge Metrics
    const METRIC_BADGE_TOTAL = 'total';
    const METRIC_BADGE_REACH = 'reach';
    const METRIC_BADGE_ENGAGEMENT = 'engagement';
    const METRIC_BADGE_QUALITY = 'quality';

    public static $METRIC_QUALITY = array(
        Model_Presence::METRIC_POSTS_PER_DAY,
        Model_Presence::METRIC_LINKS_PER_DAY,
        Model_Presence::METRIC_LIKES_PER_POST
    );

    public static $METRIC_ENGAGEMENT = array(
        Model_Presence::METRIC_RATIO_REPLIES_TO_OTHERS_POSTS,
        Model_Presence::METRIC_RESPONSE_TIME
    );

    public static $METRIC_REACH = array(
        Model_Presence::METRIC_POPULARITY_PERCENT,
        Model_Presence::METRIC_POPULARITY_TIME
    );

    public static $BADGE_RANGES = array( 'week', 'month' );

    public static function ALL_BADGES_TITLE() {

        return array(
            self::METRIC_BADGE_TOTAL => 'Global Score',
            self::METRIC_BADGE_REACH => 'Reach',
            self::METRIC_BADGE_ENGAGEMENT => 'Engagement',
            self::METRIC_BADGE_QUALITY => 'Quality'
        );
    }

    public static function ALL_BADGES_METRICS() {

        return array(
            self::METRIC_BADGE_REACH => self::$METRIC_REACH,
            self::METRIC_BADGE_ENGAGEMENT => self::$METRIC_ENGAGEMENT,
            self::METRIC_BADGE_QUALITY => self::$METRIC_QUALITY
        );
    }

    public function __construct($data, $type, $title, $item, $class)
    {
        $this->type = $type;
        $this->rankType = $this->type.'_rank';
        $this->title = $title;
        $this->class = $class;
        $this->item = $item;

        if($this->class == 'Model_Presence'){
            $this->presences = array($item);
        } else {
            $this->presences = $item->getPresences();
        }

        foreach($this->presences as $presence){
            $presence->name = $presence->handle;
        }

        $this->data = $data;
        $this->getMetrics();

        $this->rankTotal = count($this->data);

        $type = $this->type;
        $rankType = $this->rankType;

        //if this item exists in the data->score array set the score, otherwise set to 0
        if(array_key_exists($item->id, $this->data)){
            $this->score = $this->data[$item->id]->$type;
            $this->rank = $this->data[$item->id]->$rankType;
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

    private function getMetrics()
    {
        //for each presence get the values of the metrics and add them onto the Model_Presence object
        foreach($this->presences as $p => $presence){

            $data = $presence->getMetrics($this->type);

            if(count($this->presences) < 2) {

                $this->metrics = $data;
                return;

            } else {

                $this->presences[$p]->metrics = $data;
                foreach($data as $m => $metric){
                    if(!isset($this->metrics[$m])) {
                        $this->metrics[$m] = (object)array(
                            'score' => 0,
                            'type' => $m,
                            'title' => $metric->title
                        );
                    }
                    $this->metrics[$m]->score += $metric->score;
                }

            }

        }

        foreach($this->metrics as $metric){
            $metric->score /= count($this->presences);
        }
    }

}