<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 16:40
 */

namespace Outlandish\SocialMonitor\FacebookFetcher;


use Facebook\FacebookRequest;

/**
 * Object for creating Facebook Requests
 *
 * Moving the Request Factory
 *
 * Class RequestFactory
 * @package Outlandish\SocialMonitor\FacebookFetcher
 */
class RequestFactory {

    /**
     * @var SessionFactory
     */
    private $session;

    public function __construct(SessionFactory $session)
    {
        $this->session = $session;
    }

    /**
     * Creates a FacebookRequest Object
     *
     * @param $method
     * @param $endpoint
     * @param $parameters
     *
     * @return FacebookRequest
     */
    public function getRequest($method, $endpoint, $parameters = array())
    {
        return new FacebookRequest(
            $this->session->getSession(),
            $method,
            $endpoint,
            $parameters
        );
    }

}