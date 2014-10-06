<?php

abstract class Model_iProvider
{
	protected $db;
	protected $tableName;

	public function __construct(PDO $db)
	{
		$this->db = $db;
	}

	/**
	 * Fetch data for a certain handle (twitter handle, facebook name, sina weibo name etc)
	 * @param mixed $handle  The handle to fetch data for
	 * @return array         The normalized data
	 * @uses findAndSaveLinks
	 */
	abstract public function fetchData(Model_Presence $presence);

	/**
	 * Get all posts/tweets/streamdata for a specific presence between 2 dates
	 * @param iPresence $presence  The presence to get the data for
	 * @param DateTime $start      The first day to fetch the data for (inclusive)
	 * @param DataTime $end        The last day to fetch the data for (inclusive)
	 * @return array   The historic streamdata
	 */
	abstract public function getHistoricData(Model_Presence $presence, \DateTime $start, \DateTime $end);

	/**
	 * Find links in a single post/tweet/streamdatum and save them (and the corresponding domain)
	 * @param mixed $streamdatum   The streamdatum to find a link in
	 * @return int   The number of links found
	 */
	abstract protected function findAndSaveLinks($streamdatum);

	/**
	 * Test if a handle is retrievable
	 * @param mixed $handle  The handle to test
	 * @return array|false Return false when handle is invalid, otherwise an array with the following data in order: `type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`
	 */
	abstract public function testHandle($handle);
}