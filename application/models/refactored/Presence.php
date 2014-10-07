<?php

class NewModel_Presence
{
	protected $provider;
	protected $db;
	protected $metrics;

	//these should be public to mimic existing Presence Class
	public $id;
	public $handle;
	public $type;
	public $label;
	public $uid;
	public $sign_off;
	public $branding;
	public $popularity;
	public $klout_score;
	public $facebook_engagement;
	public $page_url;
	public $image_url;
	public $owner;

	public function __construct(PDO $db, array $internals, NewModel_iProvider $provider, array $metrics = array())
	{
		$this->db = $db;
		$this->provider = $provider;
		$this->metrics = $metrics;

		if (!array_key_exists('id', $internals)) {
			throw new \InvalidArgumentException('Missing id for Presence');
		}
		if (!array_key_exists('handle', $internals)) {
			throw new \InvalidArgumentException('Missing handle for Presence');
		}
		$this->id = $internals['id'];
		$this->handle = $internals['handle'];
		$this->type = $internals['type'];
		$this->label = $internals['name'];
		$this->uid = $internals['uid'];
		$this->sign_off = $internals['sign_off'];
		$this->branding = $internals['branding'];
		$this->popularity = $internals['popularity'];
		$this->klout_score = $internals['klout_score'];
		$this->facebook_engagement = $internals['facebook_engagement'];
		$this->page_url = $internals['page_url'];
		$this->image_url = $internals['image_url'];
	}

	public function getId()
	{
		return $this->id;
	}

	public function getHandle()
	{
		return $this->handle;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getLabel()
	{
		return $this->label;
	}

	public function getPageUrl()
	{
		return $this->page_url;
	}

	public function getImageUrl()
	{
		return $this->page_url;
	}

	public function getUID()
	{
		return $this->uid;
	}

	public function getSignOff()
	{
		return $this->sign_off;
	}

	public function getBranding()
	{
		return $this->branding;
	}

	public function getKloutScore()
	{
		return $this->klout_score;
	}

	public function getPopularity()
	{
		return $this->popularity;
	}

	public function getFacebookEngagement()
	{
		return $this->facebook_engagement;
	}

	public function getPresenceSign()
	{
		return $this->provider->getSign();
	}

	public function isForTwitter()
	{
		return $this->getType() == NewModel_PresenceType::TWITTER();
	}

	public function isForFacebook()
	{
		return $this->getType() == NewModel_PresenceType::FACEBOOK();
	}

	public function getOwner()
	{
		if(!$this->owner){
			$stmt = $this->db->prepare('SELECT campaign_id FROM campaign_presences WHERE presence_id = :pid');
			$stmt->execute(array(':pid'=>$this->getId()));
			$campaignId = $stmt->fetchColumn(0);
			if($campaignId === false) return null;
			$this->owner = Model_Campaign::fetchById($campaignId);
		}
		return $this->owner;
	}

	public function getTargetAudience()
	{
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getTargetAudience();
		}
		$target = 0;
		$owner = $this->getOwner();
		if($owner){
			$target = $owner->getTargetAudience();
			$target *= BaseController::getOption($this->isForFacebook() ? 'fb_min' : 'tw_min');
			$target /= 100;
		}
		return $target;
	}

	public function getKpiData()
	{
		if($this->getType() != NewModel_PresenceType::SINA_WEIBO()){
			$presence = Model_Presence::fetchById($this->getId());
			return $presence->getKpiData();
		}
		throw new \LogicException("Not implemented yet.");
	}

	public function getHistoricData(\DateTime $start, \DateTime $end) {
		return $this->provider->getHistoricData($this, $start, $end);
	}

	public function update() {
		$data = $this->provider->fetchData($this);

	}
}