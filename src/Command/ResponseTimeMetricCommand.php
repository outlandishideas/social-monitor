<?php

namespace Outlandish\SocialMonitor\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This command generates a response time metric score for your chosen presence
 *
 * Class ResponseTimeMetricCommand
 * @package Outlandish\SocialMonitor\Command
 */
class ResponseTimeMetricCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:metric:response-time')
            ->setDescription('Generates a response time metric score for your chosen presence')
            ->addArgument(
                'presence-id',
                InputArgument::REQUIRED,
                'The id of the presence that you want to view the response time metric score for'
            )
            ->addOption(
                'date',
                'c',
                InputOption::VALUE_REQUIRED,
                'The date to the view of the response time metric score for',
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

        /** @var \Metric_ResponseTime $metric */
        $metric = $this->getContainer()->get('metric.response-time');

        /** @var \Model_Presence $presence */
        $presence = \Model_PresenceFactory::getPresenceById($presenceId);

        $data = $presence->getResponseData($then, $now);

        foreach ($data as $row) {
            $output->writeln("Created Time: {$row->created}. Difference: {$row->diff}.");
        }

        $value = $metric->calculate($presence, $then, $now);

        $output->writeln("[{$presenceId}] {$presence->getName()} get a value of {$value}");

        if (is_null($value)) {
            $score =  null;
        }
        if (empty($value)) {
            $score =  0;
        } else {
            $score = 100 - round(100 * $value / $metric->target);
        }

        $output->writeln("[{$presenceId}] {$presence->getName()} scored {$score}");
    }

}