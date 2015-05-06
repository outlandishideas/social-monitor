<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use Model_PresenceFactory;
use Outlandish\SocialMonitor\FacebookApp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateStatusesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:presence:feed')
            ->setDescription('Get the feed of the presence')
            ->addArgument(
                'id',
                InputArgument::OPTIONAL,
                'The id of the presence'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');

        foreach ($this->getPresences($id) as $presence) {
            $presence->fetch();
            $presence->save();
        }

    }

    /**
     * If id is null, returns all presences, else returns the presence for the given id
     *
     * If the id provided is not a valid id it will return null in an array.
     *
     * @param $id
     * @return array|\Model_Presence[]
     */
    protected function getPresences($id)
    {
        return $id ? [Model_PresenceFactory::getPresenceById($id)] : Model_PresenceFactory::getPresences();
    }
}