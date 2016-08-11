<?php

$zf2uri = ['/api/', '/oauth', '/users', '/rules', '/about', '/info', '/pulse',
    '/log/', '/map', '/donate', '/museum', '/factory/', '/cutaway', '/brands',
    '/inbox', '/new/', '/articles', '/feedback', '/mosts', '/restorepassword',
    '/voting', '/twins', '/ban/', '/forums', '/category', '/moder/index',
    '/moder/users', '/chart', '/pictures', '/comments', '/moder/perspectives',
    '/moder/traffic', '/moder/hotlink', '/moder/comments', '/moder/twins',
    '/moder/rights', '/registration', '/login', '/picture/', '/telegram',
    '/account', '/moder/brands', '/moder/museum', '/moder/pages',
    '/moder/factory'];

$zf2ExactUri = ['/log', '/moder', '/new', '/factory', '/ban'];

$useZF2 = php_sapi_name() === 'cli';
       //|| isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] == '46.188.125.123';

if (!$useZF2) {
    if (isset($_SERVER['REQUEST_URI'])) {
        foreach ($zf2uri as $uri) {
            if (0 === strpos($_SERVER['REQUEST_URI'], $uri)) {
                $useZF2 = true;
                break;
            }
        }

        if (!$useZF2) {
            foreach ($zf2ExactUri as $uri) {
                if ($_SERVER['REQUEST_URI'] === $uri) {
                    $useZF2 = true;
                    break;
                }
            }
        }
    }
}

if ($useZF2) {
    require_once 'index.zf2.php';
} else {

    // Define path to application directory
    defined('APPLICATION_PATH')
        || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

    // Define application environment
    defined('APPLICATION_ENV')
        || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development'));

    // Ensure library/ is on include_path
    set_include_path(implode(PATH_SEPARATOR, array(
        realpath(APPLICATION_PATH . '/../library'),
        //APPLICATION_PATH . '/models',
        //get_include_path(),
    )));

    require_once realpath(APPLICATION_PATH . '/../vendor/autoload.php');

    /** Zend_Application */
    require_once 'Zend/Application.php';

    // Create application, bootstrap, and run
    $application = new Zend_Application(
        APPLICATION_ENV,
        APPLICATION_PATH . '/configs/application.ini'
    );
    $application->bootstrap()
        ->run();
}