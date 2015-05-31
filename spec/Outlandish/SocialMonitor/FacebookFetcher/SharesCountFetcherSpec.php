<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 31/05/2015
 * Time: 20:40
 */

namespace spec\Outlandish\SocialMonitor\FacebookFetcher;

use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\GraphObject;
use Outlandish\SocialMonitor\FacebookFetcher\RequestFactory;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class SharesCountFetcherSpec extends ObjectBehavior
{
    public function let(RequestFactory $requestFactory)
    {
        $this->beConstructedWith($requestFactory);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Outlandish\SocialMonitor\FacebookFetcher\SharesCountFetcher');
    }

    function it_returns_a_count_of_the_number_of_shares(RequestFactory $requestFactory,
                                                       FacebookRequest $request,
                                                       FacebookResponse $response,
                                                       GraphObject $graphObject)
    {
        $count = 100;
        $id = 'Facebook_Post';
        $requestFactory->getRequest(
            "GET",
            "/{$id}/shares",
            Argument::type('array')
        )->willReturn($request);

        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn($graphObject);
        $graphObject->getProperty('data')->willReturn($graphObject);
        $graphObject->asArray()->willReturn(array_pad([], $count, $graphObject));
        $response->getRequestForNextPage()->shouldBeCalled();

        $this->getCount($id)->shouldBe($count);
    }

    function it_uses_paging_to_get_all_shares(RequestFactory $requestFactory,
                                              FacebookRequest $request,
                                              FacebookRequest $request2,
                                              FacebookResponse $response,
                                              FacebookResponse $response2,
                                              GraphObject $graphObject)
    {
        $count = 100;
        $id = 'Facebook_Post';
        $requestFactory->getRequest(
            "GET",
            "/{$id}/shares",
            Argument::type('array')
        )->willReturn($request);

        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn($graphObject);
        $graphObject->getProperty('data')->willReturn($graphObject);
        $graphObject->asArray()->willReturn(array_pad([], $count, $graphObject));
        $response->getRequestForNextPage()->willReturn($request2);

        $request2->execute()->willReturn($response2);
        $response2->getGraphObject()->willReturn($graphObject);
        $response2->getRequestForNextPage()->willReturn(null);

        $this->getCount($id)->shouldBe($count*2);
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
        $graphObject->getProperty('data')->willReturn(null);

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