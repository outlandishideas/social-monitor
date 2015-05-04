<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use Outlandish\SocialMonitor\FacebookApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FacebookPageCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:facebook:page')
            ->setDescription('Get the data from a facebook page')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'The Id of the Facebook Page'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');

        /** @var FacebookApp $facebook */
        $facebook = $this->getContainer()->get('facebook.app');

        $page = $facebook->getPage($id);

        print_r($page);

        $output->writeln("Got the facebook app");

    }
}