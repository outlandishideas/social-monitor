<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use DateTime;
use Exception;
use Outlandish\SocialMonitor\Engagement\EngagementMetric;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

class UpdateFacebookEngagementCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:metric:fb-engagement-update')
            ->setDescription('Updates historic facebook engagement scores to use new values over a range of days')
            ->addArgument(
                'start-date',
                InputArgument::REQUIRED,
                'Start Date (Y-m-d)'
            )
            ->addArgument(
                'end-date',
                InputArgument::REQUIRED,
                'End Date (Y-m-d)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //today's date in Y-m-d format
        $todayYmd = (new DateTime())->format("Y-m-d");

        $start = date_create_from_format('Y-m-d', $input->getArgument('start-date'));
        $end = date_create_from_format('Y-m-d', $input->getArgument('end-date'));
        $now = clone $start;

        /** @var EngagementMetric $engagementMetric */
        $engagementMetric = $this->getContainer()->get('facebook_engagement.weighted');
        /** @var \PDO $db */
        $db = $this->getContainer()->get('pdo');

        //update presence history data
        $sqlHistory = "UPDATE `presence_history`
                  SET `value` = :new_value
                  WHERE `type` = 'facebook_engagement'
                  AND `presence_id` = :presence_id
                  AND DATE(`datetime`) = :now";
        $updateHistory = $db->prepare($sqlHistory);

        //update presence data
        $sqlPresence = "UPDATE `presences`
                            SET `facebook_engagement` = :new_value
                            WHERE `id` = :presence_id";
        $updatePresence = $db->prepare($sqlPresence);

        do {
            //we get facebook engagement scores over 7 days so reset $then each time
            $then = clone $now;
            $then->modify("-7 days");

            //$now in Y-m-d format
            $nowYmd = $now->format("Y-m-d");

            $output->writeln($now->format('Y-m-d'));

            //get the fb engagement scores for this day
            $scores = $engagementMetric->getAll($now, $then);

            foreach ($scores as $id => $score) {
                $parameters = [
                    ':new_value' => $score,
                    ':presence_id' => $id,
                    ':now' => $nowYmd
                ];
                $updateHistory->execute($parameters);
                $output->writeln("  Updated Facebook Engagement for [{$id}]");
            }

            if ($nowYmd == $todayYmd) {
                foreach ($scores as $id => $score) {
                    $parameters = [
                        ':new_value' => $score,
                        ':presence_id' => $id
                    ];
                    $updatePresence->execute($parameters);
                }
            }

            //modify by one day and start all over again
            $now->modify("+1 day");

        } while ($now < $end);

    }

}