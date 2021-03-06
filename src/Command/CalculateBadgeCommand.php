<?php

namespace Outlandish\SocialMonitor\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CalculateBadgeCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:badge:calculate')
            ->setDescription('Get the badge scores for a particular presence')
            ->addArgument(
                'presence-id',
                InputArgument::REQUIRED,
                'The id of the presence that you want to view the engagement score for'
            )
            ->addOption(
                'date',
                'c',
                InputOption::VALUE_REQUIRED,
                'Date',
                date('Y-m-d')
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $presenceId = $input->getArgument('presence-id');
        $presence = \Model_PresenceFactory::getPresenceById($presenceId);
        $now = date_create_from_format('Y-m-d', $input->getOption('date'));

        foreach (\Badge_Factory::getBadges() as $badge) {
            $badgeName = $badge->getName();
            $score = $badge->calculate($presence, $now, \Enum_Period::MONTH());
            $output->writeln("The {$badgeName} badge for {$presenceId} is {$score}");
        }

    }

}