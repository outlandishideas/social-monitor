<?php

class Metric_Branding extends Metric_Abstract {

	const NAME = 'branding';
	
	public function __construct($translator)
	{
		parent::__construct($translator, self::NAME, "fa fa-tag", false);
	}


	/**
     * Returns 100 if presence has been branded correctly, else returns 0
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return int
     */
    public function calculate(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getBranding();
    }

    public function getScore(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return $presence->getBranding() == 1 ? 100 : 0;
    }

    public function getData(Model_Presence $presence, \DateTime $start, \DateTime $end)
    {
        return ['branding' => $presence->getBranding() == 1 ? 'yes' : 'no'];
    }

}