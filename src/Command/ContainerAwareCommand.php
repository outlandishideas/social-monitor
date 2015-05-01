<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 01/05/2015
 * Time: 13:46
 */

namespace Outlandish\SocialMonitor\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ContainerAwareCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct($name = null, $container = null)
    {
        $this->setContainer($container);
        parent::__construct($name);
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

}