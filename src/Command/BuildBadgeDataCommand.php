<?php

namespace Outlandish\SocialMonitor\Command;

use Badge_Factory;
use Enum_Period;
use Model_PresenceFactory;
use Outlandish\SocialMonitor\Cache\KpiCacheEntry;
use Outlandish\SocialMonitor\Command\Output\DatestampFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This should be run in a cron job daily, just after midnight. This ensures that the day's data is created, but will
 * be updated later as part of fetch
 */
class BuildBadgeDataCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:badges:build')
            ->setDescription('Ensures that the last 60 days worth of badge data is populated')
            ->addOption('force', null, InputOption::VALUE_OPTIONAL, 'Force an update of all values')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->setFormatter(new DatestampFormatter('Y-m-d H:i:s'));
        $objectCacheManager = $this->getContainer()->get('object-cache-manager');

        // make sure all KPI data is up-to-date
        $presences = Model_PresenceFactory::getPresences();
        $endDate = new \DateTime();
        $startDate = new \DateTime();
        $startDate->modify('-30 days');
        $cacheEntry = new KpiCacheEntry($startDate, $endDate);
        $presenceCount = '' . count($presences);
        foreach ($presences as $index=>$p) {
            $output->writeln('Recalculating KPIs [' . str_pad($index+1, strlen($presenceCount), ' ', STR_PAD_LEFT) . '/' . $presenceCount . '] [' . $p->getId() . '] [' . $p->getType() . '] [' . $p->getName() . ']');
            $p->updateKpiData($cacheEntry);
        }

        $output->writeln('Recalculating badges');

        // make sure that the last 60 day's worth of badge_history data exists
        $force = $input->getOption('force');
        Badge_Factory::guaranteeHistoricalData(Enum_Period::MONTH(), new \DateTime('now -60 days'), new \DateTime('now'), $output, [], $force);

        // do everything that the index page does, but using the (potentially) updated data
        $objectCacheManager->updateFrontPageData();

        // also store a non-temporary version of Badge::badgeData
        $objectCacheManager->populatePresenceBadgeData();

//		Badge_Factory::guaranteeHistoricalData(Enum_Period::WEEK(), new DateTime(), new DateTime()); //todo: uncomment this when it is needed

        $output->writeln('Recalculating badges complete');
    }

}