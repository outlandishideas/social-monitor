<?php

class NewModel_Presence
{
	protected $provider;
	protected $metrics;

	//these should be public to mimic existing Presence Class
	public $id;
	public $handle;
	public $type;

	public function __construct(array $internals, NewModel_iProvider $provider, array $metrics = array())
	{
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

	public function getHistoricData(\DateTime $start, \DateTime $end) {
		return $this->provider->getHistoricData($this, $start, $end);
	}
}