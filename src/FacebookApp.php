<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 04/05/2015
 * Time: 13:22
 */

namespace Outlandish\SocialMonitor;


use Facebook\FacebookRequest;
use Facebook\FacebookSession;

class FacebookApp
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

    public function getPage($pageId)
    {
        $request = new FacebookRequest($this->getSession(), "GET", "/{$pageId}");
        $response = $request->execute();
        return $response->getGraphObject();
    }

    private function getSession()
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