<?php

use Outlandish\SocialMonitor\Database\Database;

class FetchController extends BaseController
{
	protected static $publicActions = array('index', 'populate-presence-history');

	/**
	 * Fetches all tweets/facebook posts etc
	 */
	public function indexAction() {
        $force = $this->_request->getParam('force');
        if ($force) {
            $this->releaseLock($this->lockName('fetch'));
        }

        $db = self::db();

		$this->setupConsoleOutput();
		$lockName = $this->acquireLock();
		set_time_limit($this->config->app->fetch_time_limit);

		$id = $this->_request->getParam('id');
		if($id) {
			$presences = [Model_PresenceFactory::getPresenceById($id)];
		} else {
			$presences = Model_PresenceFactory::getPresences();
		}

        //update all presences
        $this->updatePresences($presences, $db, $lockName);

        //fetch the statuses for all presences
        $this->fetchStatuses($presences, $db, $lockName);

        //update presence history again, so we have the latest data
        $this->updatePresenceHistory($presences, $db, $lockName);

        //update list of domains from the new links added to the database
		$this->updateDomains();

        //update Facebook actors if they need updating
		//todo: fix bug with column `name` being too short for data
//        $this->updateFacebookActors();

		$this->touchLock($lockName);

		$this->log($this->translator->trans('route.fetch.log.finished')); //'Finished');
		$this->releaseLock($lockName);
	}

    /**
     * This action populates the presence history
     * Use this at midnight each day to ensure that we have data in the presence history
     * table when calculating metrics etc.
     */
    public function populatePresenceHistoryAction()
    {
        $db = self::db();
        $this->setupConsoleOutput();
        $presences = Model_PresenceFactory::getPresences();

        //update presence history again, so we have the latest data
        $this->updatePresenceHistory($presences, $db);

    }



	/**
	 * Used to clear the lock if the fetch process failed 
	 * 
	 * @user-level user
	 */
	public function clearLockAction() {
		$this->releaseLock($this->lockName('fetch'));
		Zend_Controller_Action_HelperBroker::getStaticHelper('FlashMessenger')->clearCurrentMessages();
		$this->_helper->redirector->gotoRoute(array('controller'=>'index', 'action'=>'index'));
	}

    /**
     * Fetches any 'actors' (users/groups/events/pages) for records in the facebook stream that don't exist, or are outdated
     * @param int $limit
     * @return array
     */
	public function updateFacebookActors($limit = 250) {
        $this->log($this->translator->trans('route.fetch.log.updating-facebook-actors.start'));

		$db = self::db();
		$actorQuery = $db->prepare('SELECT DISTINCT actor_id
			FROM facebook_stream AS stream
			LEFT OUTER JOIN facebook_actors AS actors ON stream.actor_id = actors.id
			WHERE actors.last_fetched IS NULL
			   OR actors.last_fetched < NOW() - INTERVAL ' . $this->config->facebook->cache_user_data . ' DAY
			ORDER BY
			   IF(actors.last_fetched IS NULL, 0, 1) ASC,
			   RAND()
			LIMIT ' . $limit);
		$actorQuery->execute();
		$actorIds = $actorQuery->fetchAll(PDO::FETCH_COLUMN);
		$inserted = array();

		if ($actorIds) {
			$insertActor = $db->prepare('REPLACE INTO facebook_actors (id, name, pic_url, profile_url, type, last_fetched)
				VALUES (:id, :name, :pic_url, :profile_url, :type, :last_fetched)');

			$actorIdsString = ' IN (' . implode(',', $actorIds) . ')';

            try {
                $mq = Util_Facebook::multiquery(array(
                    'user'=>'SELECT uid, name, pic_square, profile_url FROM user WHERE uid' . $actorIdsString,
                    'page'=>'SELECT page_id, name, pic_square FROM page WHERE page_id' . $actorIdsString,
                    'group'=>'SELECT gid, name, pic_small FROM group WHERE gid' . $actorIdsString,
                    'event'=>'SELECT eid, name, pic_square FROM event WHERE eid' . $actorIdsString
                ));
            } catch (Exception_FacebookApi $ex) {
                $this->log($this->translator->trans('route.fetch.log.updating-facebook-actors.fail', ['%message%' => $ex->getMessage()]));
                return;
            }

			$now = gmdate('Y-m-d H:i:s');
			foreach ($mq as $group) {
				$type = $group['name'];
				foreach ($group['fql_result_set'] as $item) {
					$args = array(
						':id'=>null,
						':name'=>null,
						':pic_url'=>null,
						':profile_url'=>null,
						':type'=>$type,
						':last_fetched'=>$now
					);

					switch ($type) {
						case 'user':
							$args[':id'] = $item['uid'];
							$args[':name'] = $item['name'];
							$args[':pic_url'] = $item['pic_square'];
							$args[':profile_url'] = $item['profile_url'];
							break;
						case 'page':
							$args[':id'] = $item['page_id'];
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

        $this->log($this->translator->trans('route.fetch.log.updating-facebook-actors.success', ['%count%' => count($inserted)]));
    }

	public function updateDomains() {

        $this->log($this->translator->trans('route.fetch.log.updating-domains.start'));

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
			$domainName = $this->getContainer()->getParameter('domain.name');
            if(preg_match('/'.$domainName.'/i', $domain)){
                $is_bc = 1;
            }
			try {
				$stmt->execute(array(':domain'=>$domain, ':is_bc' => $is_bc));
				$inserted[] = $domain;
			} catch (Exception $ex) {}
		}

        $this->log($this->translator->trans('route.fetch.log.updating-domains.success', ['%count%' => count($inserted)]));

    }

	protected function setupConsoleOutput() {

        parent::setupConsoleOutput();

		if (!file_exists(APP_ROOT_PATH . '/log')) {
			mkdir(APP_ROOT_PATH . '/log', 0777, true);
		}

		// backup the last log file
		@copy($this->logFileName('fetch'), $this->logFileName('fetch') . '.last');

		//truncate log file
		$action = $this->_request->getActionName();
		$action = $action == 'index' ? 'fetch' : $action;
		file_put_contents( $this->logFileName('fetch'), '');

		$this->log($this->translator->trans('route.fetch.log.start', ['%action%' => $action, '%start%' => date('Y-m-d')]) . "\n");
	}

	protected function log($message, $ignoreSilent = false) {
		$log = date('Y-m-d H:i:s') . " $message\n";

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
				$this->log($this->translator->trans('route.fetch.log.lock-acquire.already-running', ['%seconds%' => $seconds]));
			} else {
				//force show message
				$this->log($this->translator->trans('route.fetch.log.lock-acquire.stale', ['%seconds%' => $seconds, '%lockname%' => $lockName]), true);
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

    /**
     * update presence history regardless of the status of when it was last updated etc
     * we need to ensure that the presence history has the data required to make calculations
     * used in the metrics/badges etc, which doesn't happen if the presence didn't update for whatever reason
     *
     * @param $presences
     * @param Database $db
     * @param $lockName
     */
    private function updatePresenceHistory($presences, $db, $lockName = null)
    {
        $presenceCount = count($presences);
        $index = 0;
		/** @var Model_Presence $presence */
		foreach ($presences as $presence) {
            //forcefully close the DB-connection and reopen it to prevent 'gone away' errors.
            $db->closeConnection();
            $db->getConnection();
            $index++;
            $this->log($this->translator->trans('route.fetch.log.presence-history.start') . " [{$index}/{$presenceCount}] [{$presence->getType()->getTitle()}] [{$presence->getId()}] [{$presence->getHandle()}] [{$presence->getName()}]");
            try {
                // add subset of properties into presence_history table
                $presence->updateHistory();
            } catch (Exception $e) {
                $this->log($this->translator->trans('route.fetch.log.presence-history.error', ['%message%' => $e->getMessage()]));
            }
            if ($lockName) {
                $this->touchLock($lockName);
            }
        }
    }

    /**
     * @param $presences
     * @param Database $db
     * @param $lockName
     * @return array
     */
    protected function updatePresences($presences, $db, $lockName)
    {
        $infoInterval = ($this->config->presence->cache_data_hours ?: 4) * 3600;
        $presenceCount = count($presences);

        //updating presence info
        usort($presences, function (Model_Presence $a, Model_Presence $b) {
            $aVal = $a->getLastUpdated() ?: '000000';
            $bVal = $b->getLastUpdated() ?: '000000';
            return strcmp($aVal, $bVal);
        });

        $index = 0;
        /** @var Model_Presence[] $presences */
        foreach ($presences as $presence) {
			$logPrefix = "[{$index}/{$presenceCount}] [{$presence->getType()->getTitle()}] [{$presence->getId()}]";
            //forcefully close the DB-connection and reopen it to prevent 'gone away' errors.
            $db->closeConnection();
            $db->getConnection();
            $index++;
            $now = time();
            $lastUpdatedString = $presence->getLastUpdated();
            $lastUpdated = strtotime($lastUpdatedString);
			$message = " {$logPrefix} [{$presence->getHandle()}] [{$presence->getName()}] [{$presence->getEngagementValue()}]";
            if (!$lastUpdated || ($now - $lastUpdated > $infoInterval)) {
                $this->log($this->translator->trans('route.fetch.log.update-info.start', ['%message%' => $message]));
                try {
                    // update using provider
                    $presence->update();
                    // save to DB
                    $presence->save();

					$this->log($this->translator->trans('route.fetch.log.update-info.success', ['%message%' => $message]));
                } catch (Exception $e) {
                    $this->log($this->translator->trans('route.fetch.log.update-info.error', ['%message%' => $e->getMessage()]));
                }
                $this->touchLock($lockName);
                $this->log('touchLock()');
            } else {
				$this->log($this->translator->trans('route.fetch.log.update-info.skip', ['%message%' => $message, '%lastupdated%' => $lastUpdatedString]));
			}
        }
    }

	/**
	 * @param $presences
	 * @param Database $db
	 * @param $lockName
	 */
    private function fetchStatuses($presences, $db, $lockName)
    {
        $presenceCount = count($presences);

        //updating presence statuses
        usort($presences, function(Model_Presence $a, Model_Presence $b) {
            $aVal = $a->getLastFetched() ?: '000000';
            $bVal = $b->getLastFetched() ?: '000000';
            return strcmp($aVal, $bVal);
        });
        $index = 0;
		/** @var Model_Presence $presence */
		foreach($presences as $presence) {
            //forcefully close the DB-connection and reopen it to prevent 'gone away' errors.
            $db->closeConnection();
            $db->getConnection();
            $index++;
			$message = "[{$index}/{$presenceCount}] [{$presence->getType()->getTitle()}] [{$presence->getId()}] [{$presence->getHandle()}] [{$presence->getName()}]";
            $this->log($this->translator->trans('route.fetch.log.fetch-statuses.start', ['%message%' => $message]));
            try {
                $count = $presence->fetch();
                $presence->save();
                $this->log($this->translator->trans('route.fetch.log.fetch-statuses.success',['%count%' => $count]));
            } catch (Exception $e) {
                $this->log($this->translator->trans('route.fetch.log.fetch-statuses.error', ['%message%' => $e->getMessage()]));
            }
            $this->touchLock($lockName);
        }
    }
}