<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use Outlandish\SocialMonitor\Engagement\EngagementMetric;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PopulateEngagementCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:engagement-populate')
            ->setDescription('Populates the engagement for a presences history by propagating the current value')
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
        $presences = \Model_PresenceFactory::getPresences();
        $start = date_create_from_format('Y-m-d', $input->getArgument('start-date'));
        $end = date_create_from_format('Y-m-d', $input->getArgument('end-date'));
        /** @var PDO $pdo */
        $pdo = $this->getContainer()->get('pdo');
        foreach($presences as $presence) {
            $id = $presence->getId();
            $value = $presence->getEngagementValue();
            $current = clone $start;
            $type = $presence->getType()->getEngagementMetric();
            do {
                $dateStr = $current->format('Y-m-d');
                $deleteStmt = $pdo->prepare(
                    "DELETE FROM presence_history
                     WHERE DATE(datetime)='$dateStr' AND type='$type' AND presence_id=$id");
                $success = $deleteStmt->execute();
                $insertStmt = $pdo->prepare(
                    "INSERT INTO presence_history (presence_id,datetime,type,value) VALUES
                      ($id,'$dateStr','$type',$value)"
                );
                $success = $insertStmt->execute();
                $current->modify('+1 day');
            } while ($current <= $end);
            error_log("inserted history for $id");
        }

        $output->writeln("Done");

    }
}
