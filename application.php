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
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new GreetCommand());
$application->add(new CalculateAverageScoreCommand());
$application->add(new FacebookEngagementCommand());
$application->run();