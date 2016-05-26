<?php

namespace Outlandish\SocialMonitor\Command;

use Exception;
use Model_Presence;
use Model_PresenceFactory;
use Outlandish\SocialMonitor\Database\Database;
use Outlandish\SocialMonitor\Translation\Translator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This populates the presence_history table with data from the presences table
 * It should be run daily
 */
class PopulatePresenceHistoryCommand extends ContainerAwareCommand
{
	/** @var Database */
	protected $db;
	/** @var OutputInterface */
	protected $output;
	/** @var Translator */
	protected $translator;
	/** @var InputInterface */
	protected $input;

	protected function configure()
    {
        $this
            ->setName('sm:presences:populate-history')
            ->setDescription('Fetches social media data for all channels');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
		$this->init($input, $output);

		$presences = Model_PresenceFactory::getPresences();

		//update presence history again, so we have the latest data
		$this->updatePresenceHistory($presences);
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
		}
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
	}
}