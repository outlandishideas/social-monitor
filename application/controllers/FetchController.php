<?php

class FetchController extends BaseController
{
	protected $publicActions = array('index');

	/**
	 * Fetches all tweets/trends/facebook pages etc
	 */
	public function indexAction() {

		$this->setupConsoleOutput();
		$this->acquireLock();
		set_time_limit($this->config->app->fetch_time_limit);

		/** @var $presences Model_Presence[] */
		$presences = Model_Presence::fetchAll();

		/**
		 * @var $db PDO
		 */
		$db = Zend_Registry::get('db');
		$infoStmt = $db->prepare('INSERT INTO presence_history (presence_id, datetime, type, value) VALUES (:id, :datetime, :type, :value)');

		$infoInterval = ($this->config->presence->cache_data_hours ?: 4) * 3600;
		usort($presences, function($a, $b) { return strcmp($a->last_updated ?: '000000', $b->last_updated ?: '000000'); });
		foreach ($presences as $p) {
			$now = time();
			$lastUpdated = strtotime($p->last_updated);
			if (!$lastUpdated || ($now - $lastUpdated > $infoInterval)) {
				try {
					$this->log('Updating info (' . $p->type . '): ' . $p->handle);
					$p->updateInfo();
					$p->last_updated = gmdate('Y-m-d H:i:s');
					$p->save();
					$infoStmt->execute(array(
						':id'       => $p->id,
						':datetime' => gmdate('Y-m-d H:i:s'),
						':type'     => 'popularity',
						':value'    => $p->popularity
					));
				} catch (Exception_TwitterNotFound $e) {
					$this->log($e->getMessage());
					// save the fact that the user doesn't exist, so we don't try again for a while
					$p->last_updated = gmdate('Y-m-d H:i:s');
					$p->save();
				} catch (Exception_FacebookNotFound $e) {
					$this->log($e->getMessage());
					// save the fact that the user doesn't exist, so we don't try again for a while
					$p->last_updated = gmdate('Y-m-d H:i:s');
					$p->save();
				} catch (Exception $e) {
					$this->log($e->getMessage());
				}
			}
		}

		usort($presences, function($a, $b) { return strcmp($a->last_fetched ?: '000000', $b->last_fetched ?: '000000'); });
		foreach ($presences as $p) {
			$this->log('Fetching ' . ($p->type == Model_Presence::TYPE_TWITTER ? 'tweets' : 'posts') . ': ' . $p->handle);
			try {
				$this->log($p->updateStatuses());
				$p->last_fetched = gmdate('Y-m-d H:i:s');
				$p->save();
			} catch (Exception $e) {
				$this->log($e->getMessage());
			}
		}

//		//fetch lists and searches for each campaign using appropriate tokens
//		$campaigns = Model_Campaign::fetchAll();
//
//		//randomise order to avoid one campaign from blocking others
//		shuffle($campaigns);

//		$lastToken = null;
//		foreach ($campaigns as $campaign) {
//			if ($campaign->twitterToken) {
//				$this->log('Fetching tweets for campaign: '.$campaign->name);
//
//				$listsAndSearches = array_merge($campaign->activeTwitterLists, $campaign->activeTwitterSearches);
//				$this->log('Found ' . count($listsAndSearches) . ' active lists/searches');
//				$this->fetchTweets($listsAndSearches, $campaign->twitterToken);
//
//				$lastToken = $campaign->twitterToken;
//			} else {
//				$this->log('Skipping tweets for campaign with no token: '.$campaign->name."\n");
//			}
//		}
//
//		//fetch trends and facebook pages globaly rather than by campaign
//		$this->fetchAllPages();
//
//		$this->fetchAllTrends($lastToken);

		$this->touchLock();

		$this->log('Finished');
		$this->releaseLock();
	}

	private function fetchAllPages() {
		/**
		 * @var $pages Model_FacebookPage[]
		 */
//		$pages = Model_FacebookPage::fetchAll();
//		foreach ($pages as $page) {
//			try {
//				$this->log("Fetching page '{$page->name}'");
//				try {
//					$page->updateInfo();
//					$counts = $page->fetchPosts();
//					$this->log($counts);
//				} catch (FacebookApiException $e) {
//					if ($e->getCode() == 28) {
//						$this->log("Request timed out");
//						continue;
//					}
//				}
//
//				$page->posts_last_24_hours = $page->countPostsSince(gmdate('Y-m-d H:i:s', time() - 3600 * 24));
//				$page->posts_this_month = $page->countPostsSince(gmdate('Y-m-d H:i:s', gmmktime(0, 0, 0, date('m'), 1)));
//				$page->last_fetched = gmdate('Y-m-d H:i:s');
//				$page->save();
//			} catch (RuntimeException $ex) {
//				$this->log('Failed to fetch page: ' . $ex->getMessage());
//			}
//
//			$this->touchLock();
//		}
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

	/**
	 * @param $message string Send an email to the configured email address
	 */
	protected function sendNotificationEmail($message) {
		$this->sendEmail($message, 'nobody@example.com', 'The Listening Post', $this->config->app->notification_email, 'Social Dashboard Notification');
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
			// todo: disable output buffering. This doesn't work on the beta server
//			ob_start();
			echo $log;
//			while (ob_get_level()) {
//				ob_end_flush();
//			}
//			flush();
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

