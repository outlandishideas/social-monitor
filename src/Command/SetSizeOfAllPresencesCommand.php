<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace Outlandish\SocialMonitor\Command;

use Model_PresenceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetSizeOfAllPresencesCommand extends ContainerAwareCommand
{

    /**
     *
     * Facebook:
     *     Small  : p < 5000            : 48
     *     Medium : 5000 < p < 100000   : 90
     *     Large  : 100000 < p < 500000 : 39
     *     XLarge : 500000 < p
     *
     * Twitter:
     *     Small  : p < 2500          : 63
     *     Medium : 2500 < p < 50000  : 56
     *     Large  : 50000 < p         : 4
     *
     * Instagram:
     *     Small  : p < 2500          : 15
     *     Medium : 2500 < p < 50000  : 3
     *     Large  : 50000 < p         : 0
     *
     * Sina Weibo:
     *     Small  : p < 2500          : 2
     *     Medium : 2500 < p < 50000  : 3
     *     Large  : 50000 < p         : 4
     *
     * LinkedIn:
     *     Small  : p < 2500          : 0
     *     Medium : 2500 < p < 50000  : 0
     *     Large  : 50000 < p         : 1
     *
     * Youtube:
     *     Small  : p < 2500          : 45
     *     Medium : 2500 < p < 50000  : 9
     *     Large  : 50000 < p         : 2
     *
     */
    private $sizeMap = [
        'facebook' => [5000,100000,500000],
        'twitter' => [2500,50000],
        'instagram' => [2500,50000],
        'sina_weibo' => [2500,50000],
        'linkedin' => [2500,50000],
        'youtube' => [2500,50000]
    ];

    protected function configure()
    {
        $this
            ->setName('sm:presences:size')
            ->setDescription('Set the size of all presences')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $presences = Model_PresenceFactory::getPresences();

        foreach($presences as $presence) {

            $sizes = $this->sizeMap[$presence->getType()->getValue()];
            $presenceSize = 0;
            $popularity = $presence->getPopularity();
            foreach($sizes as $size) {
                if($popularity > $size) {
                    $presenceSize++;
                }
            }
            $presence->setSize($presenceSize);

            $output->writeln("Setting size of {$presence->getName()} to {$presence->getSize()} as its popularity is $popularity");

            $presence->save();

        }


    }
}