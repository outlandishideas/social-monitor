<?php

namespace Outlandish\SocialMonitor\Command;

use League\Csv\Reader;
use Outlandish\SocialMonitor\Database\Database;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This fetches all data for all presences from the social media platforms
 */
class UploadCountryDataCommand extends ContainerAwareCommand
{

	/** @var Database  */
	protected $db;

	protected function configure()
    {
        $this
            ->setName('sm:country:upload')
            ->setDescription('Upload country populate data from csv')
			->addArgument('file', InputArgument::REQUIRED);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
		$this->db = $this->getContainer()->get('db');
		$file = $input->getArgument('file');
		try {
			$reader = Reader::createFromPath($file);
		} catch (RuntimeException $e) {
			$output->writeln("File does not exist at {$file}");
			return;
		}
		$rows = $reader->fetchAssoc(0);

		$stmt = $this->db->prepare("
			UPDATE campaigns 
				SET audience = :audience,
					population = :population,
					penetration = :penetration
				WHERE country = :country;
		");

		foreach ($rows as $row) {
			$result = $stmt->execute([
				':audience' => $row['audience'],
				':population' => $row['population'],
				':penetration' => $row['penetration'],
				':country' => $row['country']
			]);
			if ($result) {
				$output->writeln("Update country: {$row['country']}");
				$output->writeln("  Update audience: {$row['audience']}");
				$output->writeln("  Update population: {$row['population']}");
				$output->writeln("  Update penetration: {$row['penetration']}");
			} else {
				$output->writeln("Could not update {$row['country']}");
			}

		}
		$command = $this->getApplication()->find('sm:object-cache:refresh');
		$command->run(new ArrayInput(['command' => 'sm:object-cache:refresh']), $output);
	}
}