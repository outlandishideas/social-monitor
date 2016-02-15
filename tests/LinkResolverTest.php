<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 27/01/2016
 * Time: 12:21
 */

namespace tests;


class LinkResolverTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_returns_the_same_url_when_there_are_no_redirects()
    {
        $link = 'http://outlandish.com';
        $url = \Util_Http::resolveUrl($link);

        $this->assertEquals($link, $url);
    }

    /** @test */
    public function it_throws_an_exception_if_the_url_is_invalid()
    {
        $link = 'http://outlandish';
        $this->setExpectedException(\RuntimeException::class);
        \Util_Http::resolveUrl($link);
    }

    /** @test */
    public function it_gets_full_url_from_a_url_shortener()
    {
        $link = 'http://tinyurl.com/6c2kpse';
        $url = \Util_Http::resolveUrl($link);
        $this->assertEquals('http://film.britishcouncil.org/our-projects/on-going-projects/festivals', $url);

        $link = 'http://tinyurl.com/6vrtwebconprogram';
        $url = \Util_Http::resolveUrl($link);
        $this->assertEquals('https://docs.google.com/document/d/12FQPMO3VkSN8CnzQohmuIm9EKK62BOPBxwdJDGji_nk/pub', $url);
    }
}