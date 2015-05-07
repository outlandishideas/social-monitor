<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 07/05/2015
 * Time: 14:16
 */

namespace Outlandish\SocialMonitor\Report;


use Outlandish\SocialMonitor\Query\BadgeRankQuerier;

interface Reportable extends BadgeRankQuerier {

    public function getType();

    public function getName();

    public function getIcon();

    public function numberOfType();

}