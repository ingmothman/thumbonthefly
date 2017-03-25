<?php

/** @var \Composer\Autoload\ClassLoader $loader */
$loader = require_once __DIR__ . "/../../vendor/autoload.php";

$uri = $_SERVER['REQUEST_URI'];
$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$uri = ltrim($uri, $currentDir);


preg_match('/(\d+)\/(\d+)\/(.*)?/', $uri, $matches);

if (count($matches) == 4) {
    $maker = new osmancode\imageonfly\ThumbsMaker();
    $maker->run($matches[1], $matches[2], $matches[3]);
}