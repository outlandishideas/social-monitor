<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CalculateAverageScoreCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('sm:score:avg')
            ->setDescription('Calculate the average score in two different ways')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $totalScore = 0;
        $allBadges = \Model_Presence::getAllBadges();
        if(count($allBadges) > 0 ){
            foreach($allBadges as $presence) {
                $totalScore += $presence['total'];
            };
            $avgScore = $totalScore / count($allBadges);

            $output->writeln("Total:   $totalScore");
            $output->writeln("Count:   {count($allBadges)}");
            $output->writeln("Average: $avgScore");
        }

        $output->writeln("No Badges to calculate with");

    }
}