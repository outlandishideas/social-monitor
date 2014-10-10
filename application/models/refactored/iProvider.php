<?php

abstract class NewModel_iProvider
{
	protected $db;
	protected $tableName;

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

		return $ret; //don't return null, just return an empty array
	}

	/**
	 * Find links in a single post/tweet/streamdatum and save them (and the corresponding domain)
	 * @param mixed $streamdatum   The streamdatum to find a link in
	 * @return int   The number of links found
	 */
	abstract protected function findAndSaveLinks($streamdatum);

	/**
	 * returns the data from handleData() without type and uid, which should not be updated
	 *
	 * @param NewModel_Presence $presence
	 * @return array|null Return null when handle is invalid, otherwise an array with the following data in order: `image_url`, `name`, `page_url`, `popularity`, `klout_id`, `klout_score`, `facebook_engagement`, `last_updated`
	 */
	public function update(NewModel_Presence $presence) {
		$data = $this->handleData($presence->getHandle());
		if($data){
			unset($data['type']);
			unset($data['handle']);
			unset($data['uid']);
		}
		return $data;
	}

	/**
	 * returns the data from handleData() without type and uid, which should not be updated
	 *
	 * @param mixed $handle
	 * @return array|null Return null when handle is invalid, otherwise an array with the following data in order: `type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `klout_id`, `klout_score`, `facebook_engagement`, `last_updated`
	 */
	public function updateNew($handle) {
		return $this->handleData($handle);
	}

	public function getKloutId() {
		return null;
	}

	/**
	 * gets the data for a handle. Returns null if handle cannot be got or throws Logic Exception if we don't know what went wrong
	 *
	 * @param mixed $handle  The handle to test
	 * @return array|null Return null when handle is invalid, otherwise an array with the following data in order: `type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `klout_id`, `klout_score`, `facebook_engagement`, `last_updated`
	 */
	abstract public function handleData($handle);
}