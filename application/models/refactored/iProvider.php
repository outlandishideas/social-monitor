<?php

abstract class NewModel_iProvider
{
	protected $db;
	protected $tableName;
	protected $sign;

	public function __construct(PDO $db)
	{
		$this->db = $db;
	}

	/**
	 * Fetch data for a certain handle (twitter handle, facebook name, sina weibo name etc)
	 * @param NewModel_Presence $presence  The handle to fetch data for
	 * @return array         The normalized data
	 * @uses findAndSaveLinks
	 */
	abstract public function fetchData(NewModel_Presence $presence);

	/**
	 * Get all posts/tweets/streamdata for a specific presence between 2 dates
	 * @param NewModel_Presence $presence  The presence to get the data for
	 * @param \DateTime $start      The first day to fetch the data for (inclusive)
	 * @param \DateTime $end        The last day to fetch the data for (inclusive)
	 * @return array   The historic streamdata
	 */
	abstract public function getHistoricStream(NewModel_Presence $presence, \DateTime $start, \DateTime $end);

	/**
	 * Get all metadata for posts/tweets/streamdata for a specific presence between 2 dates
	 * @param NewModel_Presence $presence  The presence to get the data for
	 * @param \DateTime $start      The first day to fetch the data for (inclusive)
	 * @param \DateTime $end        The last day to fetch the data for (inclusive)
	 * @return array   The historic metadata for the stream in format: array(
	 *	                    																array('date', '# posts', '#links', '#bc links'),
	 *                                     										...
	 *                                 											 )
	 */
	abstract public function getHistoricStreamMeta(NewModel_Presence $presence, \DateTime $start, \DateTime $end);

	/**
	 * Get performancedata for a specific presence between 2 dates
	 * @param NewModel_Presence $presence  The presence to get the data for
	 * @param \DateTime $start      The first day to fetch the data for (inclusive)
	 * @param \DateTime $end        The last day to fetch the data for (inclusive)
	 * @return array   The historic performancedata
	 */
	public function getHistoricData(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		$ret = array();
		$stmt = $this->db->prepare("
			SELECT * FROM `presence_history` WHERE `datetime` >= :start AND `datetime` <= :end AND `presence_id` = :id
		");
		$stmt->execute(array(
			':start'	=> $start->format('Y-m-d H:i:s'),
			':end'	=> $end->format('Y-m-d H:i:s'),
			':id'		=> $presence->getId()
		));
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);

		return count($ret) ? $ret : null;
	}

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

	public function getSign($large = false)
	{
		$classes = array($this->sign);
		if($large) $classes[] = 'large';
		return implode(' ',$classes);
	}
}