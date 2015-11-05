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

class PopulateSinaWeiboEngagementCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:sw:engagement-populate')
            ->setDescription('Populates the sina_weibo_engagement for a presences history by calculating it for each day')
            ->addArgument(
                'presence',
                InputArgument::REQUIRED,
                'The presence to add engagement to'
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
        $id = $presence->getId();
        $start = date_create_from_format('Y-m-d', $input->getArgument('start-date'));
        $end = date_create_from_format('Y-m-d', $input->getArgument('end-date'));
        $current = clone $start;

        /** @var EngagementMetric $metric */
        $metric = $this->getContainer()->get('sina_weibo.engagement.weighted');

        /** @var PDO $db */
        $db = $this->getContainer()->get('pdo');
        $sql = "INSERT INTO
                presence_history (`presence_id`, `datetime`, `type`, `value`)
                VALUES(:id, :datetime, :type, :value)
                ON DUPLICATE KEY
                UPDATE `value` = VALUES(`value`)";

        $statement = $db->prepare($sql);

        $imported = 0;

        do {
            $then = clone $current;
            $then->modify('-7 days');
            $value = floatval($metric->get($id, $current, $then));

            $success = $statement->execute([
                ':id' => $id,
                ':datetime' => $current->format('Y-m-d H:i:s'),
                ':type' => 'sina_weibo_engagement',
                ':value' => floatval($value)
            ]);
            if ($success) {
                $output->writeln("Importing {$value} as sina_weibo_engagement value for: {$current->format('Y-m-d')}");
                $imported++;
            } else {
                $output->writeln("Import failed for: {$current->format('Y-m-d')}");
            }
            $current->modify('+1 day');
        } while ($current <= $end);

        $output->writeln("Imported {$imported} values for {$id}: {$presence->getName()}");

    }
}
