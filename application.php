<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:26
 */

require __DIR__.'/bootstrap.php';

use Outlandish\SocialMonitor\Command\CalculateAverageScoreCommand;
use Outlandish\SocialMonitor\Command\FacebookEngagementCommand;
use Outlandish\SocialMonitor\Command\GreetCommand;
use Outlandish\SocialMonitor\Command\UpdatePresencesCommand;
use Outlandish\SocialMonitor\Command\UpdateStatusesCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new GreetCommand(null, $container));
$application->add(new CalculateAverageScoreCommand(null, $container));
$application->add(new FacebookEngagementCommand(null, $container));
$application->add(new UpdatePresencesCommand(null, $container));
$application->add(new UpdateStatusesCommand(null, $container));
$application->run();