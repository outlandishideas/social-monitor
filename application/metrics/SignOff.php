<?php

class Metric_SignOff extends Metric_Abstract {

	const NAME = "sign_off";
	
	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, "fa fa-check-square", false);
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
        return $presence->getSignOff();
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getSignOff() == 1 ? 100 : 0;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        // TODO: Implement getData() method.
    }


}