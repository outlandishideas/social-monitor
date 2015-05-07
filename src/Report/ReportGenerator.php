<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 07/05/2015
 * Time: 14:10
 */

namespace Outlandish\SocialMonitor\Report;


use DateTime;
use Outlandish\SocialMonitor\Query\BadgeRankDataQuery;

class ReportGenerator {

    public function generate(Reportable $reportable, DateTime $start = null, DateTime $end = null)
    {
        $end = $this->ensureEnd($end);
        $start = $this->ensureStart($start, $end);

        return new Report(new BadgeRankDataQuery(\Zend_Registry::get('db')->getConnection()), $reportable, $start, $end);
    }

    /**
     * @param DateTime $end
     * @return DateTime
     */
    protected function ensureEnd(DateTime $end)
    {
        if ($end === null) {
            $end = date_create();
            return $end;
        }
        return $end;
    }

    /**
     * @param DateTime $start
     * @param DateTime $end
     * @return DateTime
     */
    protected function ensureStart(DateTime $start, DateTime $end)
    {
        if ($start === null) {
            $start = clone $end;
            $start->modify("-30 days");
        }

        return $start;
    }

}