<?php

namespace Outlandish\SocialMonitor\Command;

use Exception;
use Model_Presence;
use Model_PresenceFactory;
use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\Models\Option;
use Outlandish\SocialMonitor\Translation\Translator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend_Config;
use Zend_Registry;

/**
 * This fetches all data for all presences from the social media platforms
 */
class FetchCommand extends ContainerAwareCommand
{
	/** @var Database */
	protected $db;
	/** @var OutputInterface */
	protected $output;
	/** @var Translator */
	protected $translator;
	/** @var string */
	protected $lock;
	/** @var InputInterface */
	protected $input;
	/** @var Zend_Config */
	protected $config;
	/** @var string */
	protected $lockName;

	protected function configure()
    {
        $this
            ->setName('sm:fetch')
            ->setDescription('Fetches social media data for all channels')
			->addOption('force', 'f', InputOption::VALUE_NONE)
			->addOption('channel', 'c', InputOption::VALUE_REQUIRED, null);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
		$this->init($input, $output);

		$this->setupLock();

		set_time_limit($this->config->app->fetch_time_limit);

		$presences = $this->getPresencesToFetch($input);

		//update all presences
		$this->updatePresences($presences);

		//fetch the statuses for all presences
		$this->fetchStatuses($presences);

		//update presence history again, so we have the latest data
		$this->updatePresenceHistory($presences);

		//update list of domains from the new links added to the database
		$this->updateDomains();

		//update Facebook actors if they need updating
		//todo: fix bug with column `name` being too short for data
//        $this->updateFacebookActors();

		$this->touchLock($this->lock);

		$this->log($this->translator->trans('route.fetch.log.finished')); //'Finished');
		$this->releaseLock($this->lock);
    }

	/**
	 * @param Model_Presence[] $presences
	 */
	protected function updatePresences($presences)
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
			$this->db->closeConnection();
			$this->db->getConnection();
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
				$this->touchLock($this->lock);
			} else {
				$this->log($this->translator->trans('route.fetch.log.update-info.skip', ['%message%' => $message, '%lastupdated%' => $lastUpdatedString]));
			}
		}
	}

	/**
	 * @param Model_Presence[] $presences
	 */
	private function fetchStatuses($presences)
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
			$this->db->closeConnection();
			$this->db->getConnection();
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
			$this->touchLock($this->lock);
		}
	}

	/**
	 * update presence history regardless of the status of when it was last updated etc
	 * we need to ensure that the presence history has the data required to make calculations
	 * used in the metrics/badges etc, which doesn't happen if the presence didn't update for whatever reason
	 *
	 * @param $presences
	 */
	private function updatePresenceHistory($presences)
	{
		$presenceCount = count($presences);
		$index = 0;
		/** @var Model_Presence $presence */
		foreach ($presences as $presence) {
			//forcefully close the DB-connection and reopen it to prevent 'gone away' errors.
			$this->db->closeConnection();
			$this->db->getConnection();
			$index++;
			$this->log($this->translator->trans('route.fetch.log.presence-history.start') . " [{$index}/{$presenceCount}] [{$presence->getType()->getTitle()}] [{$presence->getId()}] [{$presence->getHandle()}] [{$presence->getName()}]");
			try {
				// add subset of properties into presence_history table
				$presence->updateHistory();
			} catch (Exception $e) {
				$this->log($this->translator->trans('route.fetch.log.presence-history.error', ['%message%' => $e->getMessage()]));
			}

			$this->touchLock($this->lock);
		}
	}

	protected function updateDomains() {

		$this->log($this->translator->trans('route.fetch.log.updating-domains.start'));

		$inserted = array();
		$stmt = $this->db->prepare('SELECT DISTINCT l.domain
			FROM status_links AS l
			LEFT OUTER JOIN domains AS d ON l.domain = d.domain
			WHERE d.id IS NULL');
		$stmt->execute();
		$toInsert = $stmt->fetchAll(PDO::FETCH_COLUMN);
		$stmt = $this->db->prepare('INSERT INTO domains (domain, is_bc) VALUES (:domain, :is_bc)');
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

	protected function lockName($name)
	{
		return $name . '_lock';
	}

	private function releaseLock($lockName) {
		$this->setOption($lockName . '_last', $this->getOption($lockName));
		$this->setOption($lockName, '');
	}

	private function touchLock($lockName) {
		$this->setOption($lockName, time());
	}

	private function getOption($lockName)
	{
		return Option::getOption($lockName);
	}

	private function setOption($name, $value)
	{
		Option::setOption($name, $value);
	}

	private function log($message)
	{
		$this->output->writeln($message);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function init(InputInterface $input, OutputInterface $output)
	{
		$this->db = $this->getContainer()->get('db');
		$this->input = $input;
		$this->output = $output;
		$this->translator = $this->getContainer()->get('translation.translator');
		$this->config = Zend_Registry::get('config');
		$this->lockName = $this->lockName('fetch');
	}

	protected function setupLock()
	{
		$force = $this->input->getOption('force');
		if ($force) {
			$this->releaseLock($this->lockName);
		}
		$this->lock = $this->acquireLock();
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

	/**
	 * @param InputInterface $input
	 * @return array|\Model_Presence[]
	 */
	protected function getPresencesToFetch(InputInterface $input)
	{
		$id = $input->getOption('channel');
		if ($id) {
			$presences = [Model_PresenceFactory::getPresenceById($id)];
			return $presences;
		} else {
			$presences = Model_PresenceFactory::getPresences();
			return $presences;
		}
	}

}