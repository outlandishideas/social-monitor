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
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');
        /** @var PDO $pdo */
        $pdo = $this->getContainer()->get('pdo');
        $deleteStmt = $pdo->prepare(
            "DELETE FROM presence_history
                     WHERE DATE(datetime)>'$startStr'
                     AND DATE(datetime)<'$endStr'
                     AND type IN ('facebook_engagement','sina_weibo_engagement','instagram_engagement','linkedin_engagement','youtube_engagement')");
        $success = $deleteStmt->execute();
        if(!$success) {
            $error = $deleteStmt->errorInfo();
        }
        foreach($presences as $presence) {
            $id = $presence->getId();
            $value = $presence->getEngagementValue();
            $current = clone $start;
            $type = $presence->getType()->getEngagementMetric();
            $values = array();
            do {
                $dateStr = $current->format('Y-m-d');
                $values[] = "($id,'$dateStr','$type',$value)";
                $current->modify('+1 day');
            } while ($current <= $end);
            $values = implode(",",$values);
            $insertStmt = $pdo->prepare(
                "INSERT INTO presence_history (presence_id,datetime,type,value) VALUES
                      $values"
            );
            $success = $insertStmt->execute();
            $output->writeln("inserted history for $id");
        }

        $output->writeln("Done");

    }
}
