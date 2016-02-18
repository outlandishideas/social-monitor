<?php

use \Mockery as m;

class Model_PresenceTest extends PHPUnit_Framework_TestCase
{
    /** @var PDO */
    private $pdo;
    /** @var Provider_Twitter */
    private $provider;
    /** @var array */
    private $internals;

    protected function setUp()
    {
        parent::setUp();
        $this->pdo = m::mock(PDO::class);
        $this->provider = m::mock(Provider_Twitter::class);
        $this->internals = $internals = [
            'id' => 1,
            'handle' => 'handle',
            'type' => Enum_PresenceType::TWITTER(),
            'name' => 'name',
            'uid' => '12345678',
            'sign_off' => 1,
            'branding' => 1,
            'popularity' => 1000,
            'klout_score' => 50,
            'facebook_engagement' => null,
            'sina_weibo_engagement' => null,
            'page_url' => 'page_url',
            'image_url' => 'image_url',
            'last_updated' => '2016-02-16 18:00:00',
            'last_fetched' => '2016-02-16 18:00:00',
            'user_id' => null
        ];
    }


    /** @test */
    public function it_can_be_created()
    {
        $presence = $this->newPresence();

        $this->assertInstanceOf(Model_Presence::class, $presence);
    }
    
    /** @test */
    public function it_gets_the_target_audience_from_its_owner()
    {
        $ownerTargetAudience = 10000;
        $expected = $ownerTargetAudience * BaseController::getOption('tw_min') / 100;
        $owner = m::mock(Model_Campaign::class);
        $owner->shouldReceive('getTargetAudience')->andReturn($ownerTargetAudience);

        $presence = $this->newPresence();
        $presence->owner = $owner;
        $actual = $presence->getTargetAudience();

        $this->assertEquals($expected, $actual);
    }

    /** @test */
    public function it_uses_the_size_of_a_presence_to_determine_target_audience_from_a_group()
    {
        $ownerTargetAudience = 10000;
        $presenceCount = 2; //the number of presences of the same size belonging to the owner
        $expected = $ownerTargetAudience;
        $expected *= BaseController::getOption("size_1_presences") / 100 / $presenceCount; //the calculation to reduce target audience depending on the size and the number of presences of the same size in the same group
        $expected *= BaseController::getOption('tw_min') / 100; //reduce the target audience depending on presence type

        $presence = $this->newPresence(['size' => 1]);
        $presence2 = $this->newPresence(['size' => 1]);

        $owner = m::mock(Model_Group::class);
        $owner->shouldReceive('getTargetAudience')->andReturn($ownerTargetAudience);
        $owner->shouldReceive('getPresencesBySize')->with(1)->andReturn([$presence, $presence2]);

        $presence->owner = $owner;
        $actual = $presence->getTargetAudience();

        $this->assertEquals($expected, $actual);
    }

    private function newPresence($internals = [])
    {
        $internals = array_merge($this->internals, $internals);

        return new Model_Presence($this->pdo, $internals, $this->provider, []);
    }
}
