<?php

namespace Outlandish\SocialMonitor\Command;

use Facebook\FacebookSDKException;
use Model_PresenceFactory;
use Outlandish\SocialMonitor\PresenceType\PresenceType;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePresencesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:presence:update')
            ->setDescription('Update')
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
			->addOption(
                'history',
                null,
                InputOption::VALUE_NONE,
                'Should the presence history table be updated'
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
            $output->writeln("Updating {$presence->getName()}");
            try {
                $presence->update();
                if($input->getOption('history')) {
                    $presence->updateHistory();
                }
            } catch (\Exception_FacebookNotFound $e) {
                //do we delete this presence here
                $output->writeln("Could not find {$presence->getName()}");
                continue;
            } catch (\LogicException $e) {
                $output->writeln("Could not update {$presence->getName()}");
                continue;
            } catch (\Exception_TwitterNotFound $e) {
                $output->writeln("Could not find {$presence->getName()}");
                continue;
            } catch (FacebookSDKException $e) {
                $output->writeln("Could not update {$presence->getName()}");
                continue;
            }

            $presence->save();
            $output->writeln("{$presence->getName()} updated.");
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
        return $type ? Model_PresenceFactory::getPresencesByType(PresenceType::get($type)) : Model_PresenceFactory::getPresences();
    }

    protected function getAllPresences()
    {
        return Model_PresenceFactory::getPresences();
    }
}