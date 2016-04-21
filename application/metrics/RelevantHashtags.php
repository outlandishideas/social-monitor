<?php

class RelevantHashTags extends Metric_Abstract {

	const NAME = "relevant_hashtags";
	
    function __construct($translator)
    {
		parent::__construct($translator, self::NAME, "NO ICON YET", false);
        $this->target = floatval(BaseController::getOption('target_hashtags_per_week'));
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
       return count($presence->getRelevantHashtags());
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $actual = $presence->getMetricValue($this);
        return $actual;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
        return [];
    }


}