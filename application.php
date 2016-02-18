<?php

require __DIR__.'/bootstrap.php';

use Symfony\Component\Console\Application;

$application = new Application();
// add all of the commands in the Command directory
$root = __DIR__ . "/src";
$directory = new RecursiveDirectoryIterator($root . '/Command/');
foreach (new RecursiveIteratorIterator($directory) as $filename=>$current) {
    if (substr($filename, -4) == '.php') {
        $className = 'Outlandish\SocialMonitor' . str_replace([$root, '/', '.php'], ['', '\\', ''], $filename);
        try {
            $testClass = new ReflectionClass($className);
            if ($testClass->isInstantiable()) {
                $application->add(new $className(null, $container));
            }
        } catch (\Exception $ex) {
            // ignore exceptions
        }
    }
}

$application->run();