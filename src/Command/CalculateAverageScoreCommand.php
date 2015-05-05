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
            ->addOption(
                'by-groups',
                'g',
                InputOption::VALUE_NONE,
                'calculate the average by grouped values'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $totalScore = 0;
        $count = 0;

        if ( $input->hasOption('by-groups') && $input->getOption('by-groups') ) {
            $countries = \BaseController::getObjectCache('map_data_30', true);
            $smallCountries = \BaseController::getObjectCache('small_country_data_30', true);
            $groups = \BaseController::getObjectCache('group_data_30', true);

            foreach ([$countries, $smallCountries, $groups] as $source) {
                foreach($source as $item) {
                    if ($item->id == -1) {
                        continue;
                    }
                    $totalScore += $item->b->total->{30}->s;
                    $count += 1;
                }
            }

            $avgScore = $totalScore / $count;

            $output->writeln("Total:   $totalScore");
            $output->writeln("Count:   $count");
            $output->writeln("Average: $avgScore");
        } else {
            $allBadges = \Model_Presence::getAllBadges();
            if(count($allBadges) > 0 ){
                foreach($allBadges as $presence) {
                    $totalScore += $presence['total'];
                };
                $count = count($allBadges);
                $avgScore = $totalScore / $count;

                $output->writeln("Total:   $totalScore");
                $output->writeln("Count:   $count");
                $output->writeln("Average: $avgScore");
            } else {
                $output->writeln("No Badges to calculate with");
            }

        }


    }
}