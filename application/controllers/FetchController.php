<?php

class FetchController extends BaseController
{
	protected static $publicActions = array('index');

	/**
	 * Fetches all tweets/trends/facebook pages etc
	 */
	public function indexAction() {
		$this->setupConsoleOutput();
		$lockName = $this->acquireLock();
		set_time_limit($this->config->app->fetch_time_limit);

		$presences = NewModel_PresenceFactory::getPresences();
		$presenceCount = count($presences);

		$db = self::db();
		$infoStmt = $db->prepare('INSERT INTO presence_history (presence_id, datetime, type, value) VALUES (:id, :datetime, :type, :value)');

		$infoInterval = ($this->config->presence->cache_data_hours ?: 4) * 3600;
		usort($presences, function($a, $b) { return strcmp($a->last_updated ?: '000000', $b->last_updated ?: '000000'); });
		$index = 0;
		foreach ($presences as $p) {
			$index++;
			$now = time();
			$lastUpdated = strtotime($p->last_updated);
			if (!$lastUpdated || ($now - $lastUpdated > $infoInterval)) {
				$saveLastUpdated = true;
				try {
					$this->log('Updating ' . $p->type . ' info (' . $index . '/' . $presenceCount . '): ' . $p->handle);
					$p->updateInfo();
					$infoStmt->execute(array(
						':id'       => $p->id,
						':datetime' => gmdate('Y-m-d H:i:s'),
						':type'     => 'popularity',
						':value'    => $p->popularity
					));
                    if($p->isForTwitter() && $p->klout_score){
                        $infoStmt->execute(array(
                            ':id'       => $p->id,
                            ':datetime' => gmdate('Y-m-d H:i:s'),
                            ':type'     => 'klout_score',
                            ':value'    => $p->klout_score
                        ));
                    }
                    if($p->isForFacebook() && $p->facebook_engagement){
                        $infoStmt->execute(array(
                                ':id'       => $p->id,
                                ':datetime' => gmdate('Y-m-d H:i:s'),
                                ':type'     => 'facebook_engagment_score',
                                ':value'    => $p->facebook_engagement
                            ));
                    }
				} catch (Exception_TwitterNotFound $e) {
					$this->log($e->getMessage());
				} catch (Exception_FacebookNotFound $e) {
					$this->log($e->getMessage());
				} catch (Exception $e) {
					$this->log("Error updating presence info: " . $e->getMessage());
					$saveLastUpdated = false;
				}

				if ($saveLastUpdated) {
					$p->last_updated = gmdate('Y-m-d H:i:s');
					$p->save();
				}
			}
			$this->touchLock($lockName);
		}

		usort($presences, function($a, $b) { return strcmp($a->last_fetched ?: '000000', $b->last_fetched ?: '000000'); });
		$index = 0;
		foreach ($presences as $p) {
			$index++;
			$this->log('Fetching ' . ($p->isForTwitter() ? 'tweets' : 'posts') . ' (' . $index . '/' . $presenceCount . '): ' . $p->handle);
			try {
				$this->log($p->updateStatuses());
				$p->refetchStatusInfo();
				$p->last_fetched = gmdate('Y-m-d H:i:s');
				$p->save();
			} catch (Exception $e) {
				$this->log("Error updating presence statuses: " . $e->getMessage());
			}
			$this->touchLock($lockName);
		}

		$this->log('Updating linked domains');
		$inserted = $this->updateDomains();
		$this->log('Inserted ' . count($inserted) . ' domains');

		$this->log('Updating facebook actors');
		try {
			$inserted = $this->updateFacebookActors();
		} catch (Exception_FacebookApi $ex) {
			$this->log('Failed to update facebook actors: ' . $ex->getMessage());
		}
		$this->log('Updated ' . count($inserted) . ' actors');

		$this->touchLock($lockName);

		$this->log('Finished');
		$this->releaseLock($lockName);
	}

	/**
	 * @user-level user
	 */
	public function clearLockAction() {
		$this->releaseLock($this->lockName('fetch'));
		Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger')->clearCurrentMessages();
		$this->_helper->redirector->gotoRoute(array('controller'=>'index', 'action'=>'index'));
	}

	/**
	 * Fetches any 'actors' (users/groups/events/pages) for records in the facebook stream that don't exist, or are outdated
	 */
	public function updateFacebookActors($limit = 250) {
		$db = self::db();
		$actorQuery = $db->prepare('SELECT DISTINCT actor_id
			FROM facebook_stream AS stream
			LEFT OUTER JOIN facebook_actors AS actors ON stream.actor_id = actors.id
			WHERE actors.last_fetched IS NULL OR actors.last_fetched < NOW() - INTERVAL ' . $this->config->facebook->cache_user_data . ' DAY
			ORDER BY RAND()
			LIMIT ' . $limit);
		$actorQuery->execute();
		$actorIds = $actorQuery->fetchAll(PDO::FETCH_COLUMN);
		$inserted = array();

		if ($actorIds) {
			$insertActor = $db->prepare('REPLACE INTO facebook_actors (id, username, name, pic_url, profile_url, type, last_fetched)
				VALUES (:id, :username, :name, :pic_url, :profile_url, :type, :last_fetched)');

			$actorIdsString = ' IN (' . implode(',', $actorIds) . ')';
			$mq = Util_Facebook::multiquery(array(
				'user'=>'SELECT uid, username, name, pic_square, profile_url FROM user WHERE uid' . $actorIdsString,
				'page'=>'SELECT page_id, username, name, pic_square FROM page WHERE page_id' . $actorIdsString,
				'group'=>'SELECT gid, name, pic_small FROM group WHERE gid' . $actorIdsString,
				'event'=>'SELECT eid, name, pic_square FROM event WHERE eid' . $actorIdsString
			));

			$now = gmdate('Y-m-d H:i:s');
			foreach ($mq as $group) {
				$type = $group['name'];
				foreach ($group['fql_result_set'] as $item) {
					$args = array(
						':id'=>null,
						':username'=>null,
						':name'=>null,
						':pic_url'=>null,
						':profile_url'=>null,
						':type'=>$type,
						':last_fetched'=>$now
					);

					switch ($type) {
						case 'user':
							$args[':id'] = $item['uid'];
							$args[':username'] = $item['username'];
							$args[':name'] = $item['name'];
							$args[':pic_url'] = $item['pic_square'];
							$args[':profile_url'] = $item['profile_url'];
							break;
						case 'page':
							$args[':id'] = $item['page_id'];
							$args[':username'] = $item['username'];
							$args[':name'] = $item['name'];
							$args[':pic_url'] = $item['pic_square'];
							break;
						case 'group':
							$args[':id'] = $item['gid'];
							$args[':name'] = $item['name'];
							$args[':pic_url'] = $item['pic_small'];
							break;
						case 'event':
							$args[':id'] = $item['eid'];
							$args[':name'] = $item['name'];
							$args[':pic_url'] = $item['pic_square'];
							break;
					}

					if ($args[':id']) {
						$insertActor->execute($args);
						$inserted[] = $args[':id'];
					}
				}
			}

			// create dummy entries for the missing IDs
			$diff = array_diff($actorIds, $inserted);
			foreach ($diff as $id) {
				$args = array(
					':id'=>$id,
					':username'=>null,
					':name'=>null,
					':pic_url'=>null,
					':profile_url'=>null,
					':type'=>'unknown',
					':last_fetched'=>$now
				);
				$insertActor->execute($args);
			}
		}

		return $inserted;
	}

	public function updateDomains() {
		$db = self::db();
		$inserted = array();
		$stmt = $db->prepare('SELECT DISTINCT l.domain
			FROM status_links AS l
			LEFT OUTER JOIN domains AS d ON l.domain = d.domain
			WHERE d.id IS NULL');
		$stmt->execute();
		$toInsert = $stmt->fetchAll(PDO::FETCH_COLUMN);
		$stmt = $db->prepare('INSERT INTO domains (domain, is_bc) VALUES (:domain, :is_bc)');
		foreach ($toInsert as $domain) {
            $is_bc = 0;
            if(preg_match('/britishcouncil/i', $domain)){
                $is_bc = 1;
            }
			try {
				$stmt->execute(array(':domain'=>$domain, ':is_bc' => $is_bc));
				$inserted[] = $domain;
			} catch (Exception $ex) {}
		}
		return $inserted;
	}

	/**
	 * @param $message string Send an email to the configured email address
	 */
	protected function sendNotificationEmail($message) {
		$this->sendEmail($message, 'nobody@example.com', 'The Social Media Monitor', $this->config->app->notification_email, 'Social Dashboard Notification');
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

		// backup the last log file
		@copy($this->logFileName('fetch'), $this->logFileName('fetch') . '.last');

		//truncate log file
		$action = $this->_request->getActionName();
		$action = $action == 'index' ? 'fetch' : $action;
		file_put_contents( $this->logFileName('fetch'), '');

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

		file_put_contents($this->logFileName('fetch'), $log, FILE_APPEND);
	}

	private function acquireLock($lockTimeout = 600) {
		//check for a lock and exit if one is found
		$lockName = $this->lockName('fetch');
		$lockTime = $this->getOption($lockName);
		if ($lockTime) {
			$seconds = time() - $lockTime;
			if ($seconds < $lockTimeout) {
				//show log message
				$this->log("Process already running and last active $seconds seconds ago");
			} else {
				//force show message
				$this->log("Stale lock found last active $seconds seconds ago: " . $lockName, true);
				$lastFile = $this->logFileName('fetch') . '.last';
				$staleFile = $this->logFileName('fetch') . '.stale';
				if (file_exists($lastFile) && !file_exists($staleFile)) {
					@copy($lastFile, $staleFile);
				}
			}
			exit;
		} else {
			//create lock
			$this->touchLock($lockName);
		}
		return $lockName;
	}

	private function releaseLock($lockName) {
		$this->setOption($lockName . '_last', $this->getOption($lockName));
		$this->setOption($lockName, '');
	}

	private function touchLock($lockName) {
		$this->setOption($lockName, time());
	}
}

