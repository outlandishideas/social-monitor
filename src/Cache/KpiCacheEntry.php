<?php

namespace Outlandish\SocialMonitor\Cache;

class KpiCacheEntry
{
    /** @var \DateTime */
    public $start;
    /** @var \DateTime */
    public $end;
    /** @var string */
    public $key;
    /** @var string */
    public $startString;
    /** @var string */
    public $endString;

    function __construct(\DateTime $start = null, \DateTime $end = null)
    {
        if (!$start || !$end) {
            $end = new \DateTime();
            $start = clone $end;
            $start->sub(\DateInterval::createFromDateString('30 days'));
        }

        $this->end = $end;
        $this->start = $start;
        $this->endString = $end->format('Y-m-d');
        $this->startString = $start->format('Y-m-d');
        $this->key = $this->startString . $this->endString;
    }


}