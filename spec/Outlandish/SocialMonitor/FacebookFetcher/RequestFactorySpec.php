<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 16:40
 */

namespace spec\Outlandish\SocialMonitor\FacebookFetcher;

use Outlandish\SocialMonitor\FacebookFetcher\SessionFactory;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class RequestFactorySpec extends ObjectBehavior
{
    function let(SessionFactory $sessionFactory)
    {
        $this->beConstructedWith($sessionFactory);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Outlandish\SocialMonitor\FacebookFetcher\RequestFactory');
    }
}