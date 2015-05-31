<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 15:56
 */

namespace spec\Outlandish\SocialMonitor\FacebookFetcher;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SessionFactorySpec extends ObjectBehavior
{

    function let()
    {
        $appId = 'AppId';
        $appSecret = 'AppSecret';
        $this->beConstructedWith($appId, $appSecret);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Outlandish\SocialMonitor\FacebookFetcher\SessionFactory');
    }

    function it_creates_a_facebook_session_object()
    {
        $this->getSession()->shouldReturnAnInstanceOf('Facebook\FacebookSession');
    }

    function it_produces_the_same_facebook_session_object()
    {
        $this->getSession()->shouldEqual($this->getSession());
    }

}