<?php

use Zend\Mvc\Application;
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));
// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (__FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}
// Composer autoloading
include __DIR__ . '/../vendor/autoload.php';
if (! class_exists(Application::class)) {
    throw new RuntimeException(
            "Unable to load application.\n"
            . "- Type `composer install` if you are developing locally.\n"
            . "- Type `vagrant ssh -c 'composer install'` if you are using Vagrant.\n"
            . "- Type `docker-compose run zf composer install` if you are using Docker.\n"
            );
}

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Run the application!
Application::init(require __DIR__ . '/../config/application.config.php')->run();