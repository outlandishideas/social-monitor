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
            ->setDescription('Greet someone')
            ->addOption(
                'date',
                'c',
                InputOption::VALUE_REQUIRED,
                'Date',
                date('Y-m-d')
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = date_create_from_format('Y-m-d', $input->getOption('date'));
        $then = clone $now;
        $then->modify("-7 days");

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