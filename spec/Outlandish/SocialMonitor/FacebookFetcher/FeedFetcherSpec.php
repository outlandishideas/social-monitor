<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 01/06/2015
 * Time: 13:06
 */

namespace spec\Outlandish\SocialMonitor\FacebookFetcher;

use DateTime;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\GraphObject;
use GuzzleHttp\Message\Request;
use Outlandish\SocialMonitor\FacebookFetcher\PostParser;
use Outlandish\SocialMonitor\FacebookFetcher\RequestFactory;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class FeedFetcherSpec extends ObjectBehavior
{
    function let(RequestFactory $requestFactory, PostParser $parser)
    {
        $this->beConstructedWith($requestFactory, $parser);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Outlandish\SocialMonitor\FacebookFetcher\FeedFetcher');
    }

    function it_fetches_a_feed_from_a_page_and_returns_an_array_of_posts(RequestFactory $requestFactory,
                                                                         FacebookRequest $request,
                                                                         FacebookResponse $response,
                                                                         GraphObject $graphObject,
                                                                         PostParser $parser)
    {
        $id = "Facebook_Page";
        $since = new DateTime();

        $requestFactory->getRequest(
            "GET",
            "/Facebook_Page/feed",
            Argument::type('array')
        )->willReturn($request);

        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn($graphObject);
        $graphObject->getPropertyAsArray('data')->willReturn([$graphObject]);
        $parser->parse($graphObject)->willReturn([]);

        $this->getFeed($id, $since)->shouldBeArray();
    }

    function it_returns_an_empty_array_if_the_response_is_null(RequestFactory $requestFactory,
                                                               FacebookRequest $request)
    {
        $id = "Facebook_Page";
        $since = new DateTime();

        $requestFactory->getRequest(Argument::cetera())->willReturn($request);
        $request->execute()->willReturn(null);

        $this->getFeed($id, $since)->shouldBeArray();
    }

    function it_returns_an_empty_array_if_the_response_has_no_data(RequestFactory $requestFactory,
                                                                   FacebookRequest $request,
                                                                   FacebookResponse $response)
    {
        $id = "Facebook_Page";
        $since = new DateTime();

        $requestFactory->getRequest(Argument::cetera())->willReturn($request);
        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn(null);

        $this->getFeed($id, $since)->shouldBeArray();
    }

    function it_returns_an_empty_array_if_the_graph_object_has_no_data(RequestFactory $requestFactory,
                                                                       FacebookRequest $request,
                                                                       FacebookResponse $response,
                                                                       GraphObject $graphObject)
    {
        $id = "Facebook_Page";
        $since = new DateTime();

        $requestFactory->getRequest(Argument::cetera())->willReturn($request);
        $request->execute()->willReturn($response);
        $response->getGraphObject()->willReturn($graphObject);
        $graphObject->getPropertyAsArray('data')->willReturn([]);

        $this->getFeed($id, $since)->shouldBeArray();
    }

}