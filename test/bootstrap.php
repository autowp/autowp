<?php

error_reporting(E_ALL);
if (class_exists('PHPUnit_Runner_Version', true)) {
    $phpUnitVersion = PHPUnit_Runner_Version::id();
    if ('@package_version@' !== $phpUnitVersion && version_compare($phpUnitVersion, '4.0.0', '<')) {
        echo 'This version of PHPUnit (' . PHPUnit_Runner_Version::id() . ') is not supported.'
           . ' Supported is version 4.0.0 or higher.' . PHP_EOL;
        exit(1);
    }
    unset($phpUnitVersion);
}

/**
 * Start output buffering, if enabled
 */
if (defined('TESTS_AUTOWP_OB_ENABLED') && constant('TESTS_AUTOWP_OB_ENABLED')) {
    ob_start();
}

require __DIR__ . '/../vendor/autoload.php';

require_once 'Zend/Application.php';

$application = new Zend_Application(
        null,
        dirname(__FILE__) . '/_files/application.ini'
);
$application->bootstrap();



$bootstrap = $application->getBootstrap();

$front = $bootstrap->getResource('FrontController');

$front->setParam('bootstrap', $bootstrap);

$request = new Zend_Controller_Request_Http('http://www.autowp.ru');
$front->setRequest($request);