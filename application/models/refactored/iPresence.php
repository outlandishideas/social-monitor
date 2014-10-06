<?php

abstract class iPresence
{
	protected $provider;
	protected $metrics;

	protected $id;
	protected $handle;

	public function __construct(array $internals, iProvider $provider, array $metrics = array())
	{
		$this->provider = $provider;
		$this->metrics = $metrics;

		if (!array_key_exists('id', $intenals)) {
			throw new \InvalidArgumentException('Missing id for Presence');
		}
		if (!array_key_exists('handle', $intenals)) {
			throw new \InvalidArgumentException('Missing handle for Presence');
		}
		$this->id = $internals['id'];
		$this->handle = $internals['handle'];
	}

	public function getId()
	{
		return $this->id;
	}

	public function getHandle()
	{
		return $this->handle;
	}

	public function getHistoricData(\DateTime $start, \DateTime $end) {
		return $this->provider->getHistoricData($this, $start, $end);
	}
}