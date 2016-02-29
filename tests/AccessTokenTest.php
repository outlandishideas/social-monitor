<?php

namespace tests;

use Carbon\Carbon;
use Outlandish\SocialMonitor\Models\AccessToken;

class AccessTokenTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $accessToken = new AccessToken('token', 123456789);
        $this->assertInstanceOf(AccessToken::class, $accessToken);
    }

    /** @test */
    public function it_can_determine_whether_it_has_expired_or_not()
    {
        $dateInFuture = Carbon::now()->addMonth();
        $dateInPast = Carbon::now()->subMonth();
        $validToken = new AccessToken('token', $dateInFuture);
        $expiredToken = new AccessToken('token', $dateInPast);

        $this->assertTrue($expiredToken->isExpired());
        $this->assertFalse($validToken->isExpired());
    }

    /** @test */
    public function it_can_determine_whether_the_token_will_expire_soon()
    {
        $expiresSoon = new AccessToken('token', Carbon::now()->addDays(5));
        $this->assertTrue($expiresSoon->expiresSoon());
    }
}