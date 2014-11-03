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
     * @param NewModel_Presence $presence The presence to get the data for
     * @param \DateTime $start The first day to fetch the data for (inclusive)
     * @param \DateTime $end The last day to fetch the data for (inclusive)
     * @param string $type The type of data to get. Use null to get all
     * @return array   The historic performancedata
     */
	public function getHistoricData(NewModel_Presence $presence, \DateTime $start, \DateTime $end, $type = null)
	{
        $clauses = array(
            '`datetime` >= :start',
            '`datetime` <= :end',
            '`presence_id` = :id'
        );
        $args = array(
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'	=> $end->format('Y-m-d H:i:s'),
            ':id' => $presence->getId()
        );
        if ($type) {
            $clauses[] = '`type` = :type';
            $args[':type'] = $type;
        }
		$stmt = $this->db->prepare("
			SELECT *
			FROM `presence_history`
			WHERE " . implode(' AND ', $clauses));
		$stmt->execute($args);
		$ret = $stmt->fetchAll(PDO::FETCH_ASSOC);

		return $ret;
	}

    /**
     * @param NewModel_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public abstract function getResponseData(NewModel_Presence $presence, DateTime $start, DateTime $end);

    /**
     * @param string $type
     * @param array $links map of status_id=>links
     */
    protected function saveLinks($type, $links) {
        $links = array_filter($links);
        if (!$links) {
            return;
        }
        $insertStmt = $this->db->prepare('INSERT INTO status_links
            (type, status_id, url, domain)
            VALUES
            (:type, :status_id, :url, :domain)');
        foreach ($links as $statusId => $urls) {
            foreach ($urls as $url) {
                try {
                    $url = Util_Http::resolveUrl($url);
                    $domain = Util_Http::extractDomain($url);
                    $insertStmt->execute(array(
                        ':type' => $type,
                        ':status_id' => $statusId,
                        ':url' => $url,
                        ':domain' => $domain
                    ));
                } catch (Exception $ex) {
                    // do nothing
                }
            }
        }
    }

    /**
	 * Updates the presence
	 *
	 * @param NewModel_Presence $presence
	 */
	public function update(NewModel_Presence $presence) {
		$this->updateMetadata($presence);
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