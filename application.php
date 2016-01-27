<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:26
 */

require __DIR__.'/bootstrap.php';

use Outlandish\SocialMonitor\Command\CalculateAverageScoreCommand;
use Outlandish\SocialMonitor\Command\CalculateBadgeCommand;
use Outlandish\SocialMonitor\Command\EngagementBadgeCommand;
use Outlandish\SocialMonitor\Command\FacebookEngagementCommand;
use Outlandish\SocialMonitor\Command\GreetCommand;
use Outlandish\SocialMonitor\Command\PopulatePopulationCommand;
use Outlandish\SocialMonitor\Command\PopulatePopulationFromCurrentCommand;
use Outlandish\SocialMonitor\Command\PopulateSinaWeiboEngagementCommand;
use Outlandish\SocialMonitor\Command\ReportDownloaderCommand;
use Outlandish\SocialMonitor\Command\ReportMetricCommand;
use Outlandish\SocialMonitor\Command\ResponseTimeMetricCommand;
use Outlandish\SocialMonitor\Command\SetSizeOfAllPresencesCommand;
use Outlandish\SocialMonitor\Command\SinaWeiboEngagementCommand;
use Outlandish\SocialMonitor\Command\SinaWeiboTestCommand;
use Outlandish\SocialMonitor\Command\UpdateFacebookEngagementCommand;
use Outlandish\SocialMonitor\Command\UpdatePresencesCommand;
use Outlandish\SocialMonitor\Command\UpdateStatusesCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new GreetCommand(null, $container));
$application->add(new CalculateAverageScoreCommand(null, $container));
$application->add(new SetSizeOfAllPresencesCommand(null, $container));
$application->add(new FacebookEngagementCommand(null, $container));
$application->add(new CalculateBadgeCommand(null, $container));
$application->add(new UpdatePresencesCommand(null, $container));
$application->add(new UpdateStatusesCommand(null, $container));
$application->add(new PopulatePopulationCommand(null, $container));
$application->add(new UpdateFacebookEngagementCommand(null, $container));
$application->add(new ResponseTimeMetricCommand(null, $container));
$application->add(new ReportDownloaderCommand(null, $container));
$application->add(new SinaWeiboTestCommand(null, $container));
$application->add(new SinaWeiboEngagementCommand(null, $container));
$application->add(new PopulatePopulationFromCurrentCommand(null, $container));
$application->add(new PopulateSinaWeiboEngagementCommand(null, $container));
$application->add(new ReportMetricCommand(null, $container));
$application->run();