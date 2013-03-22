<?php

class FetchController extends BaseController
{
	protected $publicActions = array('index', 'analyse', 'twitter-queue', 'job');

	/**
	 * Fetches all tweets/trends/facebook pages etc
	 */
	public function indexAction() {

		$this->setupConsoleOutput();
		$this->acquireLock();
		set_time_limit($this->config->app->fetch_time_limit);

		//fetch lists and searches for each campaign using appropriate tokens
		$campaigns = Model_Campaign::fetchAll();

		//randomise order to avoid one campaign from blocking others
		shuffle($campaigns);

		$lastToken = null;
		foreach ($campaigns as $campaign) {
			if ($campaign->twitterToken) {
				$this->log('Fetching tweets for campaign: '.$campaign->name);

				$listsAndSearches = array_merge($campaign->activeTwitterLists, $campaign->activeTwitterSearches);
				$this->log('Found ' . count($listsAndSearches) . ' active lists/searches');
				$this->fetchTweets($listsAndSearches, $campaign->twitterToken);

				$lastToken = $campaign->twitterToken;
			} else {
				$this->log('Skipping tweets for campaign with no token: '.$campaign->name."\n");
			}
		}

		//fetch trends and facebook pages globaly rather than by campaign
		$this->fetchAllPages();

		$this->fetchAllTrends($lastToken);

		$this->touchLock();

		//fetch klout for users in lists
		$this->log('Updating Klout scores');
		$this->fetchKlout(Model_TwitterUser::fetchNoKlout(), true);

		$this->touchLock();

		//fetch peerindex
		$this->log('Updating PeerIndex scores');
		$this->fetchPeerindex(Model_TwitterUser::fetchNoPeerindex(), true);

		$this->log('Finished');
		$this->releaseLock();
	}

	private function fetchAllPages() {
		/**
		 * @var $pages Model_FacebookPage[]
		 */
		$pages = Model_FacebookPage::fetchAll();
		foreach ($pages as $page) {
			try {
				$this->log("Fetching page '{$page->name}'");
				try {
					$page->updateInfo();
					$counts = $page->fetchPosts();
					$this->log($counts);
				} catch (FacebookApiException $e) {
					if ($e->getCode() == 28) {
						$this->log("Request timed out");
						continue;
					}
				}

				$page->posts_last_24_hours = $page->countPostsSince(gmdate('Y-m-d H:i:s', time() - 3600 * 24));
				$page->posts_this_month = $page->countPostsSince(gmdate('Y-m-d H:i:s', gmmktime(0, 0, 0, date('m'), 1)));
				$page->last_fetched = gmdate('Y-m-d H:i:s');
				$page->save();
			} catch (RuntimeException $ex) {
				$this->log('Failed to fetch page: ' . $ex->getMessage());
			}

			$this->touchLock();
		}
	}

	/**
	 * @param Model_TwitterBase[] $listsAndSearches
	 * @param Model_TwitterToken $token
	 */
	private function fetchTweets($listsAndSearches, $token) {
		foreach ($listsAndSearches as $thing) {
			try {

				$this->log("Fetching tweets from {$thing->type}: {$thing->label}");
				$counts = $thing->fetchTweets($token);
				$this->log($counts);

				if ($thing->type == 'list') {
					$refetchCounts = $thing->refetchTweets($token);
					foreach ($refetchCounts as $name=>$count) {
						$items = 'tweet' . ($count == 1 ? '' : 's');
						$this->log("Refetched $count $items from $name");
					}
				}

			} catch (Exception_TwitterApi $e) {
				$httpCode = $e->getCode();
				$this->log("Received $httpCode error from Twitter API. Skipping {$thing->type}.");
				continue;
			} catch (Exception $e) {
				print_r($e);
			}

			$thing->tweets_last_24_hours = $thing->countTweetsSince(gmdate('Y-m-d H:i:s', time() - 3600 * 24));
			$thing->tweets_this_month = $thing->countTweetsSince(gmdate('Y-m-d H:i:s', gmmktime(0, 0, 0, date('m'), 1)));
			$thing->last_fetched = gmdate('Y-m-d H:i:s');
			$thing->save();

			$this->touchLock();
		}
	}

	private function fetchAllTrends($token) {
		$regions = Model_TwitterTrendRegion::fetchAll();
		$fetched = array();
		foreach ($regions as $region) {
			// don't fetch trends for the same region more than once
			if (array_key_exists($region->woeid, $fetched)) {
				$region->last_fetched = $fetched[$region->woeid]->last_fetched;
			} else {
				$this->log("Fetching trends for '{$region->name}'");
				try {
					$count = $region->fetchTrends($token);
					$this->log("Fetched $count trends");
					$fetched[$region->woeid] = $region;
				} catch (Exception_TwitterApi $e) {
					$this->log("API failed with HTTP code ".$e->getCode());
				}
			}
			$region->save();
		}
	}
	
	public function analyseAction() {

		$this->setupConsoleOutput();
		$this->acquireLock();
		set_time_limit($this->config->openamplify->max_duration);
		$endTime = time() + $this->config->openamplify->max_duration;

		$campaigns = Model_Campaign::fetchAll();
		foreach ($campaigns as $campaign) {
			//only process campaign if analysis is enabled
			if ($campaign->analysis_quota) {
				$this->log("Processing campaign $campaign->name");
				$this->analyseThings($campaign, 'Model_TwitterList', $endTime);
				$this->touchLock();
				$this->analyseThings($campaign, 'Model_TwitterSearch', $endTime);
				$this->touchLock();
				$this->analyseThings($campaign, 'Model_FacebookPage', $endTime);
				$this->touchLock();
			} else {
				$this->log("Skipping campaign with no quota $campaign->name");
			}
		}

		$this->log('Finished');
		$this->releaseLock();
	}
	
	private function analyseThings($campaign, $class, $endTime) {
		$failures = 0;
		$successes = 0;

		//get the number of OA requests used today
		$totalKey = 'open_amplify_' . gmdate('Y-m-d');
		$campaignKey = $totalKey.'_campaign_'.$campaign->id;
		$totalRequestsUsed = $this->getOption($totalKey) ?: 0;
		$campaignRequestsUsed = $this->getOption($campaignKey) ? : 0;

		//loop through batches of statuses until:
		// a) they're all processed, or
		// b) we go over the time limit, or
		// c) we run out of API calls for the campaign, or
		// d) we run out of overall API calls, or
		// e) we get more than max_failures failed API calls
		while (
			time() < $endTime && //b
			$totalRequestsUsed < $this->config->openamplify->daily_request_limit && //d
			$campaignRequestsUsed < $campaign->analysis_quota //c
		) {
			$statuses = $class::fetchUnanalysed($campaign, $this->config->openamplify->batch_size);
			if (!$statuses) {
				break; //a
			}
			foreach ($statuses as $status) {
				try {
					$status->extractTopics();
					$status->is_analysed = true;
					$status->save();
					$successes++;
				} catch (RuntimeException $ex) {
					$this->log('Error occurred: ' . $ex->getMessage());
					$failures++;
					if ($failures >= $this->config->openamplify->max_failures) {
						break 2; //e
					}
				}

				$totalRequestsUsed++;
				$campaignRequestsUsed++;
			}
		}

		//update the number of API calls used today
		$this->setOption($totalKey, $totalRequestsUsed);
		$this->setOption($campaignKey, $campaignRequestsUsed);

		$limits = array();
		if ($totalRequestsUsed >= $this->config->openamplify->daily_request_limit) {
			$limits['final'] = "Overall OpenAmplify limit reached.\n\nUsed $totalRequestsUsed of {$this->config->openamplify->daily_request_limit} API requests.\n\nNo more statuses will be analysed today. Limit will reset at 00:00 GMT";
		}
		if ($totalRequestsUsed >= $this->config->openamplify->daily_request_limit * 0.8) {
			$limits['warning'] = "Reached 80% of overall OpenAmplify quota.";
		}
		if ($campaignRequestsUsed >= $campaign->analysis_quota) {
			$limits['campaign_'.$campaign->id] = "OpenAmplify limit reached for campaign '$campaign->name'.\n\nUsed $campaignRequestsUsed of {$campaign->analysis_quota} API requests.\n\nNo more statuses will be analysed today. Limit will reset at 00:00 GMT";
		}

		//if we have reached a limit, send a notification email
		foreach ($limits as $name => $message) {

			//only send email once per day
			$notificationKey = $totalKey.'_sent_'.$name;
			if (!$this->getOption($notificationKey)) {
				$this->sendNotificationEmail($message);
				$this->setOption($notificationKey, 1);
			}

			$this->log('OpenAmplify '.$name.' limit reached');
		}

		$type = substr($class, 6);
		$this->log("Processed $successes statuses from $type " . ($failures ? " ($failures failed)" : ''));
	}

	/**
	 * @param $message string Send an email to the configured email address
	 */
	protected function sendNotificationEmail($message) {
		$this->sendEmail($message, 'nobody@example.com', 'The Listening Post', $this->config->app->notification_email, 'Social Dashboard Notification');
	}

	/**
	 * Called from command line to process jobs from Beanstalkd
	 */
	public function twitterQueueAction() {

		$this->setupConsoleOutput();
		set_time_limit(0);

		try {
			$this->log('Environment: '.APPLICATION_ENV);
			$this->log('Waiting for jobs...');

			/** @var $db Zend_Db_Adapter_Pdo_Mysql */
			$db = Zend_Registry::get('db');
			$db->closeConnection();

			//loop forever, waiting for jobs
			while($job = Model_Job::reserve()) {
				$data = $job->getData();
				$jobType = $job->getType();
				$this->log('Processing job #' . $job->getId() . ': ' . $jobType);

				/** @var $token Model_TwitterToken */
				$token = Model_TwitterToken::fetchById($data['token_id']);

				try {
					try {
						switch ($jobType) {
							case 'fetch_related_users' :
								/** @var $users Model_TwitterUser[] */
								$users = Model_TwitterUser::fetchById($data['ids'], $token);
								$type = $data['relation_type'];
								foreach ($users as $user) {
									if ($user->relatedUpToDate($type)) {
										$this->log('IDs already up to date for '.$user->screen_name);
									} else {
										try {
											$ids = $user->fetchRelatedIds($type, $token);
											$this->log('Fetched '.count($ids).' IDs for '.$user->screen_name);
										} catch (Exception_TwitterNotFound $nfe) {
											$this->log('User '.$user->screen_name.' does not exist. Deleted.');
											$user->delete();
										}
									}
								}

								$this->log('Finished job #'.$job->getId());
								$job->complete();

								break;
							default :
								$this->log('Deleted unknown job type');
								$job->delete();
						}


					} catch (Exception_TwitterApi $e) {
						if ($e->getCode() == Model_TwitterStatusCodes::TOO_MANY_REQUESTS) {
							//requeue job with delay
							$limits = $token->getRateLimit($e->getPath());
							$delay = $limits['reset'] - time() + 10;
							$job->release(Pheanstalk::DEFAULT_PRIORITY, $delay);
							$this->log('Requeued with delay until '.date('H:i:s', $limits['reset']));
						} else {
							$this->log('Unhandled Twitter Exception: '.$e->getCode());
							$job->delete();
						}
					} catch (Pheanstalk_Exception_ServerException $e) {
						list($type) = explode(':', $e->getMessage());
						if ($type == 'NOT_FOUND') {
							$this->log('Job timed out');
						} else {
							throw $e;
						}
					}
				} catch (Exception $e) {
					$this->log('Unhandled exception: ' . $e->getMessage());
					$this->log('Deleting job #' . $job->getId());
					$job->delete();
				}
				$db->closeConnection();
			}
		} catch (Pheanstalk_Exception $e) {
			$this->log($e->getMessage());
			$this->log('Exiting');
			return;
		}
	}

	public function jobAction() {
		if (PHP_SAPI != 'cli') die ('Must be run on the command line');

		$this->setupConsoleOutput();

		if (!$this->_request->getParam('id')) {
			$this->log('Please supply a job id');
			exit;
		}

		try {
			$job = Model_Job::fetchById($this->_request->getParam('id'));

			switch ($this->_request->getParam('do')) {
				case 'delete' :
					$job->delete();
					$this->log('Job deleted');
					break;
				case 'view' :
				default:
					print_r($job->getData());
			}
		} catch (Pheanstalk_Exception $ex) {
			$this->log('Beanstalk error: '.$ex->getMessage());
		}

	}

	protected function setupConsoleOutput() {

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->layout()->disableLayout();

		if (PHP_SAPI != 'cli'){
			header('Content-Type: text/plain');

			//output 1k of data to trigger rendering on client side, unless using CLI
			if (!$this->_request->getParam('silent')) {
				echo str_repeat(" ", 1024);
			}
		}

		//disable output buffering
		for ($i = 0; $i <= ob_get_level(); $i++) {
			ob_end_flush();
		}
		ob_implicit_flush(true);

		//truncate log file
		$action = $this->_request->getActionName();
		$action = $action == 'index' ? 'fetch' : $action;
		file_put_contents( $this->logFileName(), '');

		$this->log('Starting '.$action.' process on ' . date('Y-m-d') . "\n");
	}

	protected function log($message, $ignoreSilent = false) {
		$log = date('H:i:s') . " $message\n";

		if (!$this->_request->getParam('silent') || $ignoreSilent) {
			echo $log;
		}

		file_put_contents($this->logFileName(), $log, FILE_APPEND);
	}

	private function lockFileName($name = null) {
		$name = $name ? : $this->_request->getActionName();
		return APP_ROOT_PATH . '/log/' . $name . '.lock';
	}

	private function logFileName($name = null) {
		$name = $name ? : $this->_request->getActionName();
		return APP_ROOT_PATH . '/log/' . $name . '.log';
	}

	private function acquireLock($lockTimeout = 600) {
		//check for a lock file and exit if one is found
		$lockFile = $this->lockFileName();
		if (file_exists($lockFile)) {
			$seconds = time() - filemtime($lockFile);
			if ($seconds < $lockTimeout) {
				//show log message
				$this->log("Process already running and last active $seconds seconds ago");
			} else {
				//force show message
				$this->log("Stale lock file found last active $seconds seconds ago: " . $lockFile, true);
			}
			exit;
		} else {
			//create lock file
			touch($lockFile);
		}

		return $lockFile;
	}

	private function releaseLock() {
		$lockFileName = $this->lockFileName();
		rename($lockFileName, $lockFileName.'.last');
	}

	private function touchLock() {
		touch($this->lockFileName());
	}
}

