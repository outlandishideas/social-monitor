<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 29/04/2015
 * Time: 14:33
 */

namespace {
    require_once(__DIR__ . '/../../lib/sina_weibo/sinaweibo.php');
}

namespace Outlandish\SocialMonitor\Command {


    use Outlandish\SocialMonitor\FacebookEngagement\FacebookEngagementMetric;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;

    class SinaWeiboEngagementCommand extends ContainerAwareCommand
    {
        protected function configure()
        {
            $this
                ->setName('sm:sw:engagement')
                ->setDescription('Test the engagement score for sina weibo')
            ;
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $now = new \DateTime();
            $then = clone $now;
            $then->modify('-7 days');

            /** @var FacebookEngagementMetric $metric */
            $metric = $this->getContainer()->get('sina_weibo.engagement.weighted');
            $scores = $metric->getAll($now, $then);

            foreach ($scores as $id => $score) {
                $output->writeln("{$id} - {$score}");
            }
        }
    }
}