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



    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Output\OutputInterface;

    class SinaWeiboTestCommand extends ContainerAwareCommand
    {
        protected function configure()
        {
            $this
                ->setName('sm:sw:test')
                ->setDescription('Test the connection to sina weibo')
            ;
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $connection = new \SaeTClientV2('1247980630', 'cfcd7c7170b70420d7e1c00628d639c2', '2.00cBChiFql593B4e223582cb04rzB6');
            if (!array_key_exists('REMOTE_ADDR', $_SERVER)) {
                $connection->set_remote_ip('127.0.0.1');
            }

            /** @var \Model_Presence $presence */
            $presence = \Model_PresenceFactory::getPresenceById('396');
            $output->writeln("Testing presence details");
            $response = $connection->show_user_by_name($presence->getHandle());
            print_r($response);

            $output->writeln("Testing presence statuses");
            $response = $connection->friends_timeline(1, 200, 0);
            print_r($response);

            $output->writeln("Test over");
        }
    }
}