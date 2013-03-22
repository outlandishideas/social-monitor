<?php
/**
 * Wraps Pheanstalk job object with some application logic
 * @method release()
 */
class Model_Job {

	/**
	 * @var Pheanstalk
	 */
	protected static $pheanstalk;

	protected static $defaultTube;

	/**
	 * Beanstalk timeout (TTR) for reserved jobs, after which job is returned to queue
	 */
	const TIMEOUT = 500;

	/**
	 * @var Pheanstalk_Job
	 */
	protected $job;

	protected function __construct($job) {
		$this->job = $job;
	}

	/**
	 * @return Pheanstalk
	 */
	public static function getPheanstalk() {
		if (!isset(self::$pheanstalk)) {
			require_once APP_ROOT_PATH . '/lib/pheanstalk/pheanstalk_init.php';
			self::$pheanstalk = new Pheanstalk('127.0.0.1');
			self::$defaultTube = 'twitter_' . APPLICATION_ENV;
			self::$pheanstalk->useTube(self::$defaultTube)->watch(self::$defaultTube);
		}

		return self::$pheanstalk;
	}

	/**
	 * @param string $type Type of job
	 * @param array $data Extra job data
	 * @param string $tube
	 * @return int ID of new job
	 */
	public static function putJob($type, $data, $tube = null) {
		$pheanstalk = self::getPheanstalk();
		if (!$tube) $tube = self::$defaultTube;
		return $pheanstalk->putInTube(
			$tube,
			serialize(array($type, $data)),
			Pheanstalk::DEFAULT_PRIORITY,
			Pheanstalk::DEFAULT_DELAY,
			self::TIMEOUT
		);
	}

	/**
	 * Get a job by ID (peek) but do not reserve it
	 * @param $id
	 * @return Model_Job
	 */
	public static function fetchById($id) {
		return new self(self::getPheanstalk()->peek($id));
	}

	/**
	 * @return int Job ID
	 */
	public function getId() {
		return $this->job->getId();
	}

	/**
	 * @return array Unserialised job data
	 */
	public function getData() {
		list($type, $data) = unserialize($this->job->getData());
		return $data;
	}

	/**
	 * @return string Job type
	 */
	public function getType() {
		list($type, $data) = unserialize($this->job->getData());
		return $type;
	}

	/**
	 * @return array Job stats including state and time left
	 */
	public function stats() {
		return self::getPheanstalk()->statsJob($this->job);
	}

	/**
	 * Called when a job completes
	 */
	public function complete() {
		$subscriptions = $this->getJobSubscriptions();
		foreach ($subscriptions as $subscription) {
			$tube = 'user_updates_' . $subscription->user_id . '_' . APPLICATION_ENV;
			self::putJob('job_complete', array('originalJobData' => $this->getData()), $tube);
			$subscription->delete();
		}
		self::getPheanstalk()->delete($this->job);
	}

	/**
	 * Delete any subscriptions when deleting job
	 */
	public function delete() {
		$subscriptions = $this->getJobSubscriptions();
		foreach ($subscriptions as $subscription) {
			$subscription->delete();
		}
		self::getPheanstalk()->delete($this->job);
	}

	/**
	 * Get subscriptions for this job
	 * @return Model_JobSubscription[]
	 */
	public function getJobSubscriptions() {
		$db = Zend_Registry::get('db');
		$stmt = $db->prepare('SELECT * FROM job_subscriptions WHERE job_id = :id');
		$stmt->execute(array(':id' => $this->getId()));
		return Model_JobSubscription::objectify($stmt);
	}

	/**
	 * Proxy job method calls to Pheanstalk instance
	 * @param $method
	 * @param $args
	 */
	public function __call($method, $args) {
		array_unshift($args, $this->job);
		call_user_func_array(array(self::getPheanstalk(), $method), $args);
	}

	/**
	 * Get next job and set state to reserved, block until a job is available
	 * @return Model_Job
	 */
	public static function reserve() {
		return new self(self::getPheanstalk()->reserve());
	}

	public static function waitForJobUpdate($user) {
		$tube = 'user_updates_' . $user->id . '_' . APPLICATION_ENV;
		do {
			//only reserve for one second in order to respond to client disconnect
			$job = self::getPheanstalk()->reserveFromTube($tube, 1);
			echo ' '; //client disconnect only detected when script tries to output something
		} while (!$job);

		return new self($job);
	}
}