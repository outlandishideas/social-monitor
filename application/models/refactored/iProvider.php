<?php

abstract class NewModel_iProvider
{
	protected $db;
	protected $tableName;
    /** @var NewModel_PresenceType */
    protected $type = null;

	public function __construct(PDO $db)
	{
		$this->db = $db;
	}

	/**
	 * Fetch data for a certain handle (twitter handle, facebook name, sina weibo name etc)
	 * @param NewModel_Presence $presence  The handle to fetch data for
	 * @uses findAndSaveLinks
	 */
	abstract public function fetchStatusData(NewModel_Presence $presence);

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
		$stmt = $this->db->prepare("
			SELECT *
			FROM `presence_history`
			WHERE `datetime` >= :start
			AND `datetime` <= :end
			AND `presence_id` = :id
		");
		$stmt->execute(array(
			':start'	=> $start->format('Y-m-d H:i:s'),
			':end'	=> $end->format('Y-m-d H:i:s'),
			':id'		=> $presence->getId()
		));
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);

		return $ret;
	}

	/**
	 * Find links in a single post/tweet/streamdatum and save them (and the corresponding domain)
	 * @param mixed $streamdatum   The streamdatum to find a link in
	 * @return int   The number of links found
	 */
	abstract protected function findAndSaveLinks($streamdatum);

	/**
	 * Updates the presence
	 *
	 * @param NewModel_Presence $presence
	 */
	public function update(NewModel_Presence $presence) {
		$this->updateMetadata($presence);
        $presence->last_updated = gmdate('Y-m-d H:i:s');
	}

	public function getKloutId($uid) {
		return null;
	}

	/**
	 * gets the data for a handle. Returns null if handle cannot be got or throws Logic Exception if we don't know what went wrong
	 *
	 * @param mixed $presence The presence to update
	 * @return array|null Return null when handle is invalid, otherwise an array with the following data in order: `type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `klout_id`, `klout_score`, `facebook_engagement`, `last_updated`
	 */
	abstract public function updateMetadata(NewModel_Presence $presence);

	public function getTableName()
	{
		return $this->tableName;
	}
}