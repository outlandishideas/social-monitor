<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use Outlandish\SocialMonitor\Report\ReportablePresence;
use Outlandish\SocialMonitor\Report\ReportDownloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReportDownloaderCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:report:downloader')
            ->setDescription('Get the url to download the pdf from')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ReportDownloader $downloader */
        $downloader = $this->getContainer()->get('report.downloader');
        $presence = \Model_PresenceFactory::getPresenceById(43);
        $reportable = new ReportablePresence($presence);
        $now = new \DateTime();
        $then = clone $now;
        $then->modify("-30 days");

        $url = $downloader->getUrl($reportable, $then, $now);
        $output->writeln($url);
    }
}