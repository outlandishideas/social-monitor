<?php


class NewModel_FacebookProvider extends NewModel_iProvider
{
	protected $connection = null;

	protected $tableName = 'facebook_stream';
	protected $type = null;

	public function __construct(PDO $db) {
		parent::__construct($db);
		$this->type = NewModel_PresenceType::FACEBOOK();
	}

	public function fetchData(NewModel_Presence $presence)
	{
		if (!$presence->getUID()) {
			throw new Exception('Presence not initialised/found');
		}


		$stmt = $this->_db->prepare("SELECT created_time FROM {$this->tableName} WHERE presence_id = :id ORDER BY created_time DESC LIMIT 1");
		$stmt->execute(array(':id' => $presence->getId()));
		$since = $stmt->fetchColumn();
		if ($since) {
			$since = strtotime($since);
		}

		$posts = Util_Facebook::pagePosts($presence->getUID(), $since);
		foreach ($posts as $s) {
			$this->parseStatus($presence, $s);
		}

		return array();
	}

	protected function parseStatus(NewModel_Presence $presence, $post)
	{
		$id = $this->saveStatus($presence, $post);
		$post->local_id = $id;
		$postedByOwner = $post->actor_id == $presence->getUID();
		if ($postedByOwner) {
			$this->findAndSaveLinks($post);
		}
	}


	protected function saveStatus(NewModel_Presence $presence, $post)
	{
		$postedByOwner = $post->actor_id == $presence->getUID();
		$stmt = $this->db->prepare("
			INSERT INTO `{$this->tableName}`
			(`post_id`, `presence_id`, `message`, `create_time`, `actor_id`, `comments`,
				`likes`, `share_count`, `permalink`, `type`, `posted_by_owner`, `needs_response`, `in_response_to`)
			VALUES
			(:post_id, :presence_id, :message, :create_time, :actor_id, :comments,
				:likes, :share_count, :permalink, :type, :posted_by_owner, :needs_response, :in_response_to)
		");
		$args = array(
			'post_id' => $post->post_id,
			'presence_id' => $presence->id,
			'message' => $post->message,
			'created_time' => gmdate('Y-m-d H:i:s', $post->created_time),
			'actor_id' => $post->actor_id,
			'permalink' => $post->permalink,
			'comments' => isset($post->comments['count']) ? intval($post->comments['count']) : 0,
			'likes' => isset($post->likes['count']) ? intval($post->likes['count']) : 0,
			'share_count' => $post->share_count,
			'type' => $post->type,
			'posted_by_owner' => $postedByOwner,
			'needs_response' => !$postedByOwner && $post->message
		);
		$stmt->execute($args);
		return $this->db->lastInsertId();
	}


	public function getHistoricData(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}


	public function getHistoricStream(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}


	public function getHistoricStreamMeta(NewModel_Presence $presence, \DateTime $start, \DateTime $end)
	{
		return null;
	}


	protected function findAndSaveLinks($streamdatum)
	{
		$newLinks = $this->extractLinks($streamdatum->message);
		foreach ($newLinks as $link) {
			$link['external_id'] = $streamdatum->post_id;
//			$link['type'] = $presence->getType();
			$links[] = $link;
		}
	}

	public function testHandle($handle) {

		try {
			$data = Util_Facebook::pageInfo($handle);
		} catch (Exception_FacebookNotFound $e) {
			return false;
//			throw new Exception_FacebookNotFound('Facebook page not found: ' . $this->handle, $e->getCode(), $e->getFql(), $e->getErrors());
		}

		$this->facebook_engagement = $this->calculateFacebookEngagement();

		//test if user exists
		//todo: add facebook engagement to this
		return array(
			NewModel_PresenceType::FACEBOOK, //type
			$handle, //handle
			$data['page_id'], //uid
			$data['pic_square'], //image_url
			$data['name'], //name
			$data['page_url'], //page_url
			$data['fan_count'], //popularity
			gmdate('Y-m-d H:i:s') //last_updated
		);
	}
}