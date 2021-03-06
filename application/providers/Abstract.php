<?php

use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\PresenceType\PresenceType;

abstract class Provider_Abstract
{
	protected $db;
	protected $tableName;
    /** @var PresenceType */
    protected $type = null;
    protected $createdTimeColumn = 'created_time';
    protected $contentColumn = 'message';
    protected $engagementStatement = '(likes + comments * 4)';
	// todo: declare this properly (instead of putting specific subclasses here)
	/** @var Outlandish\SocialMonitor\Adapter\AbstractAdapter|Outlandish\SocialMonitor\Adapter\LinkedinAdapter|Outlandish\SocialMonitor\Adapter\YoutubeAdapter|Outlandish\SocialMonitor\Adapter\TwitterAdapter */
	protected $adapter;

	public function __construct(Database $db, $adapter, $type, $tableName)
	{
		$this->db = $db;
		$this->adapter = $adapter;
		$this->type = $type;
		$this->tableName = $tableName;
	}

    public function getType() {
        return $this->type;
    }

	/**
	 * Fetch data for a certain handle (twitter handle, facebook name, sina weibo name etc)
	 * @param Model_Presence $presence  The handle to fetch data for
	 */
	abstract public function fetchStatusData(Model_Presence $presence);

    /**
     * Get all posts/tweets/streamdata for a specific presence between 2 dates
     * @param Model_Presence $presence The presence to get the data for
     * @param \DateTime $start The first day to fetch the data for (inclusive)
     * @param \DateTime $end The last day to fetch the data for (inclusive)
     * @param null $search
     * @param null $order
     * @param null $limit
     * @param null $offset
     * @return object   The historic streamdata and the total count
     */
    public function getHistoricStream(Model_Presence $presence, \DateTime $start, \DateTime $end,
                                      $search = null, $order = null, $limit = null, $offset = null)
    {
        $presenceId = $presence->getId();
        $clauses = array(
            "p.$this->createdTimeColumn >= :start",
            "p.$this->createdTimeColumn <= :end",
            'p.presence_id = :id'
        );
        $args = array(
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
            ':id' => $presenceId
        );
        return $this->getHistoricStreamData($clauses,$args,$search,$order,$limit,$offset);
    }

    /**
     *
     * Get all statuses for the provided $presences, or all presences if $presences null or empty
     *
     * @param Model_Presence[] $presences
     * @param DateTime $start
     * @param DateTime $end
     * @param null $search
     * @param null $order
     * @param null $limit
     * @param null $offset
     * @return object
     */
    public function getHistoricStreamMulti($presences, \DateTime $start, \DateTime $end,
                                           $search = null, $order = null, $limit = null, $offset = null)
    {
        $clauses = array(
            "p.$this->createdTimeColumn >= :start",
            "p.$this->createdTimeColumn <= :end",
        );
        $args = array(
            ':start' => $start->format('Y-m-d H:i:s'),
            ':end'   => $end->format('Y-m-d H:i:s'),
        );
        if($presences && count($presences)) {
            $ids = array_map(function($p) {
                return $p->getId();
            },$presences);
            $ids = array_unique($ids);
            $clauses[] = 'p.presence_id IN (' . implode($ids,',') . ')';
        }

        return $this->getHistoricStreamData($clauses,$args,$search,$order,$limit,$offset);
    }

    public function getHistoricStreamData($clauses, $args, $search = null, $order = null, $limit = null, $offset = null) {
        $searchArgs = $this->getSearchClauses($search, array("p.{$this->contentColumn}"));
        $clauses = array_merge($clauses, $searchArgs['clauses']);
        $args = array_merge($args, $searchArgs['args']);

        $sql = "
			SELECT SQL_CALC_FOUND_ROWS p.*
			FROM {$this->tableName} AS p
			WHERE " . implode(' AND ', $clauses);
        $sql .= $this->getOrderSql($order, array('date'=>$this->createdTimeColumn, 'engagement'=>$this->engagementStatement));
        $sql .= $this->getLimitSql($limit, $offset);

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute($args);
        if(!$success) {
            error_log('error fetching statuses:'.implode(',',$stmt->errorInfo()));
        }
		$total = $this->db->lastRowCount();
        $ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $objects = $this->parseStatuses($ret);

        $this->decorateStreamData($ret);

        return (object)array(
            'stream' => $objects,
            'total' => $total
        );
    }

    protected function decorateStreamData(&$statuses) {
        return;
    }

    /**
     * Get all metadata for posts/tweets/streamdata for a specific presence between 2 dates
     * @param Model_Presence $presence The presence to get the data for
     * @param \DateTime $start The first day to fetch the data for (inclusive)
     * @param \DateTime $end The last day to fetch the data for (inclusive)
     * @param bool $ownPostsOnly
     * @return array The historic metadata for the stream in format: array(
     *                                                                                        array('date', '# posts', '#links', '#bc links'),
     * ...
     * )
     */
	abstract public function getHistoricStreamMeta(Model_Presence $presence, \DateTime $start, \DateTime $end, $ownPostsOnly = false);

    /**
     * Get performancedata for a specific presence between 2 dates
     * @param Model_Presence $presence The presence to get the data for
     * @param \DateTime $start The first day to fetch the data for (inclusive)
     * @param \DateTime $end The last day to fetch the data for (inclusive)
     * @param string $type The type of data to get. Use null to get all
     * @return array   The historic performancedata
     */
	public function getHistoricData(Model_Presence $presence, \DateTime $start, \DateTime $end, $type = null)
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
			WHERE " . implode(' AND ', $clauses) . "
            ORDER BY datetime DESC
        ");
		$stmt->execute($args);
		$ret = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		return $ret;
	}

    /**
     * @param Model_Presence $presence
     * @param DateTime $start
     * @param DateTime $end
     * @return array
     */
    public abstract function getResponseData(Model_Presence $presence, DateTime $start, DateTime $end);

    public function getStatusStream(Model_Presence $presence, $start, $end, $search, $order, $limit, $offset)
    {
        return $this->getHistoricStream($presence, $start, $end, $search, $order, $limit, $offset);
    }

    public function getStatusStreamMulti($presences, $start, $end, $search, $order, $limit, $offset)
    {
        return $this->getHistoricStreamMulti($presences, $start, $end, $search, $order, $limit, $offset);
    }

    /**
     * Run a simple test on the adapter to see if we can fetch the presence
     *
     * Override this method if this needs to be run before creating a new presence.
     *
     * @param Model_Presence $presence
     * @return null
     */
    public function testAdapter(Model_Presence $presence)
    {
        return null;
    }

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
     * Save all hashtags of a post to to the hashtags table and link them to the post with the posts_hashtags table
     * @param string $type
     * @param array $post
     * @param int $postId
     */
    protected function saveHashtags($type, $post, $postId){

        if(!$post->hashtags || !$post->posted_by_owner){
            return;
        }

        foreach ($post->hashtags as $hashtag){
            try{
                // Start a transaction because we write to two different tables that are interdependent
                $this->db->beginTransaction();

                // Check if the hashtag already exists in the database because it is unique
                $selectStmt = $this->db->prepare("SELECT id FROM hashtags WHERE hashtag=:hashtag");
                $selectStmt->execute(array(':hashtag' => $hashtag));
                $hashtagId = $selectStmt->fetch()['id'];

                // If the hashtag does not yet exist in the table insert it 
                if (!$hashtagId){
                    $hashtagStmt = $this->db->prepare("INSERT INTO hashtags (hashtag) VALUES (:hashtag)");
                    $hashtagStmt->execute(array(':hashtag' => $hashtag));
                    $hashtagId = $this->db->lastInsertId();
                }

                // Insert into linking table
                $postsStmt = $this->db->prepare("INSERT INTO posts_hashtags (post, hashtag, post_type) VALUES (:post, :hashtag, :post_type)");
                $postsStmt->execute(array(
                    ':post' => $postId,
                    ':hashtag' => $hashtagId,
                    ':post_type' => $type
                ));

                $this->db->commit();
            }catch (Exception $e){
                $this->db->rollBack();
                error_log('Error inserting hashtags into table' . $e->getMessage());
            }
        }
    }

    /**
	 * Updates the presence
	 *
	 * @param Model_Presence $presence
	 */
	public function update(Model_Presence $presence) {
		$this->updateMetadata($presence);
	}

	/**
	 * gets the data for a handle. Returns null if handle cannot be got or throws Logic Exception if we don't know what went wrong
	 *
	 * @param mixed $presence The presence to update
	 * @return array|null Return null when handle is invalid, otherwise an array with the following data in order: `type`, `handle`, `uid`, `image_url`, `name`, `page_url`, `popularity`, `klout_id`, `klout_score`, `facebook_engagement`, `last_updated`
	 */
	abstract public function updateMetadata(Model_Presence $presence);

	public function getTableName()
	{
		return $this->tableName;
	}

    protected function getSearchClauses($search, $columns) {
        $clauses = array();
        $args = array();
        if ($search && $columns) {
            $matches = array();
            foreach ($columns as $column) {
                $matches = array("$column LIKE :search");
            }
            $clauses[] = '(' . implode(' OR ', $matches) . ')';
            $args[':search'] = "%$search%";
        }
        return array('clauses'=>$clauses, 'args'=>$args);
    }

    protected function getOrderSql($order, $validColumns) {
        if (!is_null($order) && count($order) > 0) {
            $ordering = array();
            foreach ($order as $column=>$dir) {
                if (array_key_exists($column, $validColumns)) {
                    $ordering[] = $validColumns[$column] . ' ' . $dir;
                }
            }
            if (!is_null($ordering) && count($ordering) > 0) {
                return ' ORDER BY ' . implode(',', $ordering);
            }
        }
        return '';
    }

    protected function getLimitSql($limit, $offset) {
        if (!is_null($limit)) {
            if (is_null($offset)) {
                $offset = 0;
            }
            return ' LIMIT '.$offset.','.$limit;
        }
        return '';
    }

    /**
     * Returns all links from the given statuses, grouped by status ID
     * @param array $statusIds
     * @param string $type
     * @return array
     */
    protected function getLinks($statusIds, $type) {
        $links = array();
        if ($statusIds) {
            $placeholders = implode(',', array_fill(0, count($statusIds), '?'));
            $linksStmt = $this->db->prepare("
                SELECT sl.*, d.is_bc
                FROM status_links AS sl
                  INNER JOIN domains AS d ON sl.domain = d.domain
                WHERE
                    status_id IN ({$placeholders})
                    AND type = '{$type}'
                ORDER BY is_bc DESC");
            $linksStmt->execute($statusIds);
            foreach ($linksStmt->fetchAll(\PDO::FETCH_ASSOC) as $link) {
                $statusId = $link['status_id'];
                if (!isset($links[$statusId])) {
                    $links[$statusId] = array();
                }
                $links[$statusId][] = $link;
            }
        }
        return $links;
    }

    /**
     * Returns all relevant hashtags posted in the given time frame
     * @param $presenceId
     * @param $start
     * @param $end
     * @return array
     */
    public function getRelevantHashtags($presenceId, $start, $end){
        return array();
    }

    /**
     * @param Model_Presence $presence  the presence to fetch the data for
     * @param DateTime $start  the date from which to fetch historic data from (inclusive)
     * @param DateTime $end  the date from which to fetch historic data to (inclusive)
     * @param array $types  the types of data to be returned from the history table (if empty all types will be returned)
     * @return array
     */
    public function getHistoryData(Model_Presence $presence, \DateTime $start, \DateTime $end, $types = [])
    {
        return [];
    }

    abstract protected function parseStatuses($raw);
}