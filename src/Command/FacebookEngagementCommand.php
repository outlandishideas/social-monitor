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

class FacebookEngagementCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:metric:facebook-engagement')
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
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Days',
                7
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Type',
                'standard'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $presenceId = $input->getArgument('presence-id');
        $now = date_create_from_format('Y-m-d', $input->getOption('date'));
        $then = clone $now;
        $days = $input->getOption('days');
        $then->modify("-$days days");

        $engagementScore = $this->getContainer()->get('facebook_engagement.standard')->get($presenceId, $now, $then);

        if ($engagementScore !== null) {
            $output->writeln("Engagement Score for $presenceId is $engagementScore");
        } else {
            $output->writeln("Could not get Engagement Score for $presenceId");
        }
    }

}