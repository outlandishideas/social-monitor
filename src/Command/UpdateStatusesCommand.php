<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use Enum_PresenceType;
use Facebook\FacebookSDKException;
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
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                'The type of presences'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        $type = $input->getOption('type');

        if($id) {
            $presences = $this->getPresences($id);
        } else if($type) {
            $presences = $this->getPresencesByType($type);
        } else {
            $presences = $this->getAllPresences();
        }

        foreach ($presences as $presence) {
            $output->writeln("Fetching statuses for {$presence->getName()}");
            try {
                $count = $presence->fetch();
            } catch (FacebookSDKException $e) {
                $output->writeln("<error>Could not fetch statuses for {$presence->getName()}</error>");
                $output->writeln("<error>Code: {$e->getCode()} :: {$e->getMessage()}</error>");
                continue;
            } catch (\Exception_TwitterNotFound $e) {
                $output->writeln("<error>{$presence->getName()} could not be found or initialised</error>");
                continue;
            } catch (\Exception $e) {
                if ($e->getMessage() == 'Presence not initialised/found') {
                    $output->writeln("<error>{$presence->getName()} could not be found or initialised</error>");
                    continue;
                }
                throw $e;
            }

            $presence->save();
            $output->writeln("Fetched {$count} statuses for {$presence->getName()}");
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

    protected function getPresencesByType($type)
    {
        return $type ? Model_PresenceFactory::getPresencesByType(Enum_PresenceType::get($type)) : Model_PresenceFactory::getPresences();
    }

    protected function getAllPresences()
    {
        return Model_PresenceFactory::getPresences();
    }
}