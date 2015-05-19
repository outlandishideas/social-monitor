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

class ResponseTimeMetricCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:metric:response-time')
            ->setDescription('Greet someone')
            ->addArgument(
                'presence-id',
                InputArgument::REQUIRED,
                'The id of the presence that you want to view the engagement score for'
            )
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
        $presenceId = $input->getArgument('presence-id');
        $now = date_create_from_format('Y-m-d', $input->getOption('date'));
        $then = clone $now;
        $then->modify("-30 days");

        /** @var \Metric_ResponseTimeNew $metric */
        $metric = \Metric_Factory::getMetric(\Metric_ResponseTimeNew::getName());

        /** @var \Model_Presence $presence */
        $presence = \Model_PresenceFactory::getPresenceById($presenceId);

        $value = $metric->calculate($presence, $then, $now);

        $output->writeln("[{$presenceId}] {$presence->getName()} get a value of {$value}");

        if (is_null($value)) {
            $score =  null;
        }
        if (empty($value)) {
            $score =  0;
        } else {
            $score = 100 - round(100 * $score / $metric->target);
        }

        $output->writeln("[{$presenceId}] {$presence->getName()} scored {$score}");


    }

}