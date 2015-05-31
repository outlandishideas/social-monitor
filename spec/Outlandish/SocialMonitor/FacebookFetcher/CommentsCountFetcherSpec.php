<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 20:09
 */

namespace spec\Outlandish\SocialMonitor\FacebookFetcher;

use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookSession;
use Facebook\GraphObject;
use Outlandish\SocialMonitor\FacebookFetcher\RequestFactory;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class CommentsCountFetcherSpec extends ObjectBehavior
{

    public function let(RequestFactory $requestFactory)
    {
        // do this here to make sure that default app has been set for specs below
        FacebookSession::setDefaultApplication('AppId', 'AppSecrent');
        $this->beConstructedWith($requestFactory);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Outlandish\SocialMonitor\FacebookFetcher\CommentsCountFetcher');
    }

    function it_returns_a_count_of_the_number_of_likes(RequestFactory $requestFactory,
                                                       FacebookRequest $request,
                                                       FacebookResponse $response,
                                                       GraphObject $graphObject)
    {
        $id = 'Facebook_Post';
        $requestFactory->getRequest(
            "GET",
            "/{$id}/comments",
            Argument::type('array')
        )->willReturn($request);

        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn($graphObject);
        $graphObject->getProperty('summary')->willReturn($graphObject);
        $graphObject->getProperty('total_count')->willReturn(100);

        $this->getCount($id)->shouldBeInteger();
    }

    function it_should_return_0_if_no_summary_data_is_returned(RequestFactory $requestFactory,
                                                               FacebookRequest $request,
                                                               FacebookResponse $response,
                                                               GraphObject $graphObject)
    {
        $id = 'Facebook_Post';
        $requestFactory->getRequest(Argument::cetera())->willReturn($request);

        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn($graphObject);
        $graphObject->getProperty('summary')->willReturn(null);

        $this->getCount($id)->shouldBe(0);
    }

    function it_should_return_0_if_total_count_is_not_in_summary(RequestFactory $requestFactory,
                                                                 FacebookRequest $request,
                                                                 FacebookResponse $response,
                                                                 GraphObject $graphObject)
    {
        $id = 'Facebook_Post';
        $requestFactory->getRequest(Argument::cetera())->willReturn($request);

        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn($graphObject);
        $graphObject->getProperty('summary')->willReturn($graphObject);
        $graphObject->getProperty('total_count')->willReturn(null);

        $this->getCount($id)->shouldBe(0);
    }

    function it_should_return_0_if_no_response_from_facebook(RequestFactory $requestFactory,
                                                             FacebookRequest $request,
                                                             FacebookResponse $response)
    {
        $id = 'Facebook_Post';
        $requestFactory->getRequest(Argument::cetera())->willReturn($request);

        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn(null);

        $this->getCount($id)->shouldBe(0);
    }

    function it_should_return_0_if_request_throws_sdk_exception(RequestFactory $requestFactory,
                                                                FacebookRequest $request)
    {
        $id = 'Facebook_Post';
        $requestFactory->getRequest(Argument::cetera())->willReturn($request);

        $request->execute()->willThrow(FacebookSDKException::class);

        $this->getCount($id)->shouldBe(0);
    }

    function it_should_return_0_if_request_throws_request_exception(RequestFactory $requestFactory,
                                                                    FacebookRequest $request)
    {
        $id = 'Facebook_Post';
        $requestFactory->getRequest(Argument::cetera())->willReturn($request);

        $request->execute()->willThrow(FacebookRequestException::class);

        $this->getCount($id)->shouldBe(0);
    }
}