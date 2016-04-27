<?php

class Metric_RelevantHashTags extends Metric_Abstract {

	const NAME = "relevant_hashtags";

    function __construct($translator)
    {
		parent::__construct($translator, self::NAME, "fa fa-hashtag", false);
        $this->target = floatval(BaseController::getOption('hashtags_per_week_best'));
    }

    /**
     * Returns 100 if presence has been signed off, else returns 0
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
       return count($presence->getRelevantHashtags($start, $end));
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        if(!$this->target){
            return null;
        }

        $hashtagCount = $presence->getMetricValue($this);
        $score = round(($hashtagCount/$this->target)*100);

        return min(max($score, 0), 100);
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
        return [];
    }


}