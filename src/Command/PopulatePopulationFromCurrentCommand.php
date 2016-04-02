<?php

namespace Outlandish\SocialMonitor\Command;

use Outlandish\SocialMonitor\Database\Database;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PopulatePopulationFromCurrentCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:popularity:populate-current')
            ->setDescription('Populates the population for a presences history from their current population')
            ->addArgument(
                'presence',
                InputArgument::REQUIRED,
                'The presence to add popularity to'
            )
            ->addArgument(
                'start-date',
                InputArgument::REQUIRED,
                'The date (Y-m-d) at which to start entering values'
            )
            ->addArgument(
                'end-date',
                InputArgument::REQUIRED,
                'The date (Y-m-d) at which to stop entering values'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $presence = \Model_PresenceFactory::getPresenceById($input->getArgument('presence'));
        $popularity = $presence->getPopularity();
        $id = $presence->getId();
        $start = date_create_from_format('Y-m-d', $input->getArgument('start-date'));
        $end = date_create_from_format('Y-m-d', $input->getArgument('end-date'));
        $current = clone $start;

        /** @var Database $db */
        $db = $this->getContainer()->get('db');
        $sql = "INSERT INTO
                presence_history (`presence_id`, `datetime`, `type`, `value`)
                VALUES(:id, :datetime, :type, :value)
                ON DUPLICATE KEY
                UPDATE `value` = VALUES(`value`)";

        $statement = $db->prepare($sql);

        $imported = 0;

        do {
            $success = $statement->execute([
                ':id' => $id,
                ':datetime' => $current->format('Y-m-d H:i:s'),
                ':type' => 'popularity',
                ':value' => $popularity
            ]);
            if ($success) {
                $output->writeln("Importing {$popularity} as popularity value for: {$current->format('Y-m-d')}");
                $imported++;
            } else {
                $output->writeln("Import failed for: {$current->format('Y-m-d')}");
            }
            $current->modify('+1 day');
        } while ($current <= $end);

        $output->writeln("Imported {$imported} values for {$id}: {$presence->getName()}");

    }
}
