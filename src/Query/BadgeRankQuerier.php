<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 07/05/2015
 * Time: 18:13
 */

namespace Outlandish\SocialMonitor\Query;


interface BadgeRankQuerier
{

    public function getColumn();

    public function getId();

    public function getCampaignTypes();

}