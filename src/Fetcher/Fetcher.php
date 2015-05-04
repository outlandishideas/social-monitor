<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 01/05/2015
 * Time: 16:39
 */

namespace Outlandish\SocialMonitor\Fetcher;


use DateTime;

interface Fetcher
{
    /**
     * Fetches the posts for the $pageId and from the $since date
     *
     * @param Fetchable $fetchable
     * @param DateTime $since
     * @return mixed
     */
    public function getPosts(Fetchable $fetchable, DateTime $since);

}