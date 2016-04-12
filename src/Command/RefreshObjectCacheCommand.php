<?php

namespace Outlandish\SocialMonitor\Command;

use Outlandish\SocialMonitor\Command\Output\DatestampFormatter;
use Outlandish\SocialMonitor\Exception\InvalidPropertiesException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshObjectCacheCommand extends ContainerAwareCommand
{
    /** @var OutputInterface */
    protected $output;

    protected function configure()
    {
        $this
            ->setName('sm:object-cache:refresh')
            ->setDescription('Refreshes the object caches')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
		try {
			$output->setFormatter(new DatestampFormatter('Y-m-d H:i:s'));
			$objectCacheManager = $this->getContainer()->get('object-cache-manager');

			$output->writeln("Updating Presence Index Table Cache...");
			$objectCacheManager->updatePresenceIndexCache();
			$output->writeln("...Done");


			$output->writeln("Updating Country Index Table Cache...");
			$objectCacheManager->updateCountryIndexCache();
			$output->writeln("...Done");


			$output->writeln("Updating Group Index Table Cache...");
			$objectCacheManager->updateGroupIndexCache();
			$output->writeln("...Done");


			$output->writeln("Updating Region Index Table Cache...");
			$objectCacheManager->updateRegionIndexCache();
			$output->writeln("...Done");


			$output->writeln("Updating Front Page Cache...");
			$objectCacheManager->updateFrontPageData();
			$output->writeln("...Done");
		} catch (InvalidPropertiesException $ex) {
			$output->writeln($ex->getProperties());
			throw $ex;
		}
    }

}