<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 15:44
 */

namespace Outlandish\SocialMonitor\FacebookFetcher;
use Facebook\FacebookSession;

/**
 * Creates a single facebook session instance to use across multiple areas
 *
 * Class SessionFactory
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
class SessionFactory
{
    private $id;
    private $secret;
    /**
     * @var FacebookSession
     */
    private $session;

    public function __construct($id, $secret)
    {
        $this->id = $id;
        $this->secret = $secret;
    }

    /**
     * returns a FacebookSession object
     *
     * If Facebook Session object has not already been created then it will
     * create it, else it returns the created instance
     *
     * @return FacebookSession
     */
    public function getSession()
    {
        if ($this->session === null) {
            FacebookSession::setDefaultApplication($this->id, $this->secret);

            $this->session = new FacebookSession($this->getAccessToken());
        }

        return $this->session;
    }

    private function getAccessToken()
    {
        return "{$this->id}|{$this->secret}";
    }

}