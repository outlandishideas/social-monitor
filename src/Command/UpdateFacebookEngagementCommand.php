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
use Outlandish\SocialMonitor\FacebookEngagement\FacebookEngagementMetric;
use Outlandish\SocialMonitor\FacebookEngagement\FacebookEngagementMetricFactory;
use Outlandish\SocialMonitor\FacebookEngagement\Query\Query;
use Outlandish\SocialMonitor\FacebookEngagement\Query\StandardFacebookEngagementQuery;
use Symfony\Component\Console\Command\Command;
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
        $start = date_create_from_format('Y-m-d', $input->getOption('start-date'));
        $end = date_create_from_format('Y-m-d', $input->getOption('end-date'));
        $now = clone $start;

        /** @var FacebookEngagementMetric $engagementMetric */
        $engagementMetric = $this->getContainer()->get('facebook_engagement.weighted');
        /** @var \PDO $db */
        $db = $this->getContainer()->get('pdo');

        $sql = "UPDATE `presence_history`
                  SET `value` = :new_value
                  WHERE `type` = 'facebook_engagement'
                  AND `presence_id` = :presence_id
                  AND DATE(`datetime`) = :now";
        $statement = $db->prepare($sql);

        do {
            //we get facebook engagement scores over 7 days so reset $then each time
            $then = clone $now;
            $then->modify("-7 days");

            $output->writeln($now->format('Y-m-d'));

            //get the fb engagement scores for this day
            $scores = $engagementMetric->getAll($now, $then);

            foreach ($scores as $id => $score) {
                $parameters = [
                    ':new_value' => $score,
                    ':presence_id' => $id,
                    ':now' => $now->format("Y-m-d")
                ];
                $statement->execute($parameters);
                $output->writeln("  Updated Facebook Engagement for [{$id}]");
            }

            //modify by one day and start all over again
            $now->modify("+1 day");

        } while ($now < $end);

        /** @var \Provider_Facebook $provider */
        $provider = \Enum_PresenceType::FACEBOOK()->getProvider();

        $presences = \Model_PresenceFactory::getPresencesByType(\Enum_PresenceType::FACEBOOK());

        $db = \Zend_Registry::get('db')->getConnection();

        $sql = "INSERT INTO presence_history (`presence_id`, `datetime`, `type`, `value`)
                VALUES (:id, :datetime, :type, :value)
        	    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

        $statement = $db->prepare($sql);

        /** @var \Model_Presence $presence */
        foreach ($presences as $presence) {
            $score = $provider->calculateFacebookEngagement($presence);
            $presence->facebook_engagement = $score;
            $presence->save();
            if ($score === null) $score = 0;
            $output->writeln("[{$presence->getId()}] {$presence->getName()} has score of {$score}");
            $statement->execute([
                ':id' => $presence->getId(),
                ':datetime' => $now->format('Y-m-d H:i:s'),
                ':type' => 'facebook_engagement',
                ':value' => $score,
            ]);
        }

    }

}