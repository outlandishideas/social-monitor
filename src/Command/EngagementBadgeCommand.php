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

class EngagementBadgeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:badge:engagement')
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
        $presence = \Model_PresenceFactory::getPresenceById($presenceId);
        $now = date_create_from_format('Y-m-d', $input->getOption('date'));

        $engagementBadge = \Badge_Factory::getBadge(\Badge_Engagement::getName());

        $score = $engagementBadge->calculate($presence, $now);

        $output->writeln("Old Engagement Score for {$presenceId} is {$score}");

        $newMetrics = [
            \Metric_Klout::getInstance(),
            \Metric_FBEngagementLeveled::getInstance(),
            \Metric_ResponseTimeNew::getInstance(),
            \Metric_ResponseRatio::getInstance()
        ];

        $weighting = [
            \Metric_Klout::getName() => '3',
            \Metric_FBEngagementLeveled::getName() => '7',
            \Metric_ResponseTimeNew::getName() => '3',
            \Metric_ResponseRatio::getName() => '2'
        ];

        $engagementBadge->setMetrics($newMetrics, $weighting);

        $score = $engagementBadge->calculate($presence, $now);
        $output->writeln("New Engagement Score for {$presenceId} is {$score}");
    }

}