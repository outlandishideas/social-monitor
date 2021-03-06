<?php

class Metric_ResponseRatio extends Metric_Abstract {

	const NAME = "response_ratio";
	
    function __construct($translator)
    {
		parent::__construct($translator, self::NAME, "fa fa-reply");
        $this->target = BaseController::getOption('replies_to_number_posts_best');
    }


    /**
     * Counts the months between now and estimated date of reaching target audience
     * calculates score based on
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return null/string
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $ret = $presence->getRatioRepliesToOthersPosts($start, $end);
        return $ret;
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        $score = $presence->getMetricValue($this);

        $score = round(100 * $score / $this->target);
        return self::boundScore($score);
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
    }


}