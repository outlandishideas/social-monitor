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

/**
 * This command copies over popularity data to fill in any days with blank data
 *
 * This command takes a required date argument, which is the date to fill in the data for.
 * It gets all the population data from the previous day and inserts it into the database
 * for the date given.
 *
 * If the previous day does not have any data it will not go back further, and will instead
 * exit with a message informing you of the case.
 *
 * Class PopulatePopulationCommand
 * @package Outlandish\SocialMonitor\Command
 */
class PopulatePopulationCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('sm:fix:popularity')
            ->setDescription('Command to fill in the blank popularity data by copying over the previous days data')
            ->addArgument(
                'date',
                InputArgument::REQUIRED,
                'The date to fill in missing results (Y-m-d)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = date_create($input->getArgument('date'));
        $previousDate = clone $date;
        $previousDate->modify("-1 day");

        $db = \Zend_Registry::get('db')->getConnection();

        $sql = "
                SELECT
                  presence_id,
                  MAX(datetime) AS datetime,
                  `type`,
                  MAX(`value`) AS value
                FROM
                  presence_history
                WHERE
                  `type` = 'popularity'
                AND
                  DATE(datetime) = :date
                GROUP BY presence_id, DATE(datetime), `type`";
        $statement = $db->prepare($sql);
        $statement->execute([':date' => $previousDate->format('Y-m-d')]);
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($data)) {
            $output->writeln("No data to move into date");
            exit;
        }

        $insertSql = "INSERT INTO presence_history (presence_id, datetime, `type`, `value`) VALUES(:presence_id, :datetime, :type, :value)";
        $statement = $db->prepare($insertSql);

        foreach ($data as $row) {
            $row['datetime'] = $date->format("Y-m-d H:i:s");
            $statement->execute([
                ':presence_id' => $row['presence_id'],
                ':datetime' => $row['datetime'],
                ':type' => $row['type'],
                ':value' => $row['value']
            ]);
        }


    }
}