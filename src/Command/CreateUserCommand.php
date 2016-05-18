<?php

namespace Outlandish\SocialMonitor\Command;

use Badge_Factory;
use Enum_Period;
use Model_PresenceFactory;
use Model_User;
use Outlandish\SocialMonitor\Cache\KpiCacheEntry;
use Outlandish\SocialMonitor\Command\Output\DatestampFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This should be run in a cron job daily, just after midnight. This ensures that the day's data is created, but will
 * be updated later as part of fetch
 */
class CreateUserCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:user:create')
            ->setDescription('Creates a user')
			->addArgument('name', InputArgument::REQUIRED, 'The username')
			->addArgument('email', InputArgument::REQUIRED, 'The email address')
			->addArgument('password', InputArgument::REQUIRED, 'The password')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Whether the user should be an admin')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
		$properties = $input->getArguments('username');
		$properties['user_level'] = $input->hasOption('admin') && $input->getOption('admin') ? 10 : 1 ;

		$user = new Model_User([]);
		$user->fromArray($properties);
		$user->save();

		$output->writeln("Created new user with the following credentials:");
		$output->writeln("Username: $user->name");
		$output->writeln("Email: $user->email");
    }

}