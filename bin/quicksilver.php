<?php

use Pantheon\Quicksilver\Config;
use Pantheon\Quicksilver\Application;

use Pantheon\Quicksilver\Command\AboutCommand;
use Pantheon\Quicksilver\Command\InstallCommand;

set_time_limit(0);

$applicationRoot = __DIR__.'/../';

if (file_exists($applicationRoot.'/vendor/autoload.php')) {
    include_once $applicationRoot.'/vendor/autoload.php';
} elseif (file_exists($applicationRoot.'/../../autoload.php')) {
    include_once $applicationRoot.'/../../autoload.php';
} else {
    echo 'Something is wrong with your application'.PHP_EOL;
    exit(1);
}

$config = new Config();
$application = new Application($config);

$application->add(new AboutCommand());
$application->add(new InstallCommand());

$defaultCommand = $config->get('application.command')?:'about';
$application->setDefaultCommand($defaultCommand);
$application->run();
