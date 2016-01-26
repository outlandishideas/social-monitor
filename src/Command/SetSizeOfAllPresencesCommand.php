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
     *     Small  : p < 5000          :
     *     Medium : 5000 < p < 100000 :
     *     Large  : 100000 < p        :
     *
     * Twitter:
     *     Small  : p < 1000          :
     *     Medium : 1000 < p < 50000  :
     *     Large  : 50000 < p         :
     *
     * Instagram:
     *     Small  : p < 1000          :
     *     Medium : 1000 < p < 50000  :
     *     Large  : 50000 < p         :
     *
     * Sina Weibo:
     *     Small  : p < 1000          :
     *     Medium : 1000 < p < 50000  :
     *     Large  : 50000 < p         :
     *
     * LinkedIn:
     *     Small  : p < 1000          :
     *     Medium : 1000 < p < 50000  :
     *     Large  : 50000 < p         :
     *
     * Youtube:
     *     Small  : p < 1000          :
     *     Medium : 1000 < p < 50000  :
     *     Large  : 50000 < p         :
     *
     */
    private $sizeMap = [
        'facebook' => [5000,100000],
        'twitter' => [1000,50000],
        'instagram' => [1000,50000],
        'sina_weibo' => [1000,50000],
        'linkedin' => [1000,50000],
        'youtube' => [1000,50000]
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