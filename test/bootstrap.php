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
 * Setup autoloading
 */
require __DIR__ . '/../vendor/autoload.php';
/**
 * Start output buffering, if enabled
 */
if (defined('TESTS_AUTOWP_OB_ENABLED') && constant('TESTS_AUTOWP_OB_ENABLED')) {
    ob_start();
}