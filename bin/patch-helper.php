#!/usr/bin/env php
<?php

// Support for different autoload paths when installed globally vs locally
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',           // Local development
    __DIR__ . '/../../autoload.php',               // Global installation via Composer
    __DIR__ . '/../../../autoload.php',            // Global installation via Composer (alternate path)
];

$autoloaderFound = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    fwrite(STDERR, "Error: Unable to find Composer autoloader.\n");
    exit(1);
}

use Symfony\Component\Console\Application;

$application = new Application();
$analyseCommand = new Ampersand\PatchHelper\Command\AnalyseCommand();
$application->add($analyseCommand);
$application->run();
exit(0);
