<?php

namespace Outlandish\SocialMonitor\Command;

use Outlandish\SocialMonitor\Command\Output\DatestampFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCacheCommand extends ContainerAwareCommand
{
    /** @var OutputInterface */
    protected $output;

    protected function configure()
    {
        $this
            ->setName('sm:cache:clear')
            ->setDescription('Clears the object caches')
            ->addOption('silent', null, InputOption::VALUE_OPTIONAL, 'Only shows essential output', false)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
    }

}