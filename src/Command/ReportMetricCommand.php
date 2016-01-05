<?php

namespace Outlandish\SocialMonitor\Command;

use Enum_PresenceType;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * This command generates reports on a particular metric
 *
 * Class ReportMetricCommand
 * @package Outlandish\SocialMonitor\Command
 */
class ReportMetricCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:metric:report')
            ->setDescription('Generates a report on a particular metric')
            ->addArgument(
                'metric',
                InputArgument::REQUIRED,
                'The name of the metric to be reported on'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'Limit the report to only those presences of a particular type'
            )
            ->addOption(
                'date',
                'd',
                InputOption::VALUE_REQUIRED,
                'The date to the view of the response time metric score for',
                date('Y-m-d')
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $metricName = $input->getArgument('metric');
        $now = date_create_from_format('Y-m-d', $input->getOption('date'));
        $then = clone $now;
        $then->modify("-30 days");

        $metric = \Metric_Factory::getMetric($metricName);

        if ($input->hasOption('type') && $input->getOption('type')) {
            /** @var Enum_PresenceType $type */
            $type = Enum_PresenceType::get($input->getOption('type'));
            if ($type->isMetricApplicable($metric)) {
                $output->writeln('metric is not applicable for this presence type');
                return;
            }
            $presences = \Model_PresenceFactory::getPresencesByType($type);
        } else {
            $presences = \Model_PresenceFactory::getPresences();
        }

        $rows = [];

        foreach ($presences as $presence) {
            if (!$presence->getType()->isMetricApplicable($metric)) {
                continue;
            }

            $row = [
                'id' => $presence->getId(),
                'presence' => $presence->getHandle()
            ];
            $row = array_merge($row, $metric->getData($presence, $then, $now));
            $row['metric_value'] = $metric->calculate($presence, $then, $now);
            $row['score'] = $metric->getScore($presence, $then, $now);

            $rows[] = $row;
        }

        $table = new Table($output);
        $table
            ->setHeaders(array_keys($rows[0]))
            ->setRows($rows);
        $table->render();
    }

}