<?php

require_once 'Zend/Tool/Project/Provider/Abstract.php';
require_once 'Zend/Tool/Project/Provider/Exception.php';

class BuildProvider extends Zend_Tool_Project_Provider_Abstract
{
    protected $_initialized = false;

    protected $_app;

    protected function _initialize()
    {
        if (!$this->_initialized) {

            $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION); //load .zfproject.xml

            $bootstrapResource = $this->_loadedProfile->search('BootstrapFile'); //get application bootstrap

            /*$env = getenv('APPLICATION_ENV');
            if (!$env) {
                throw new Exception("APPLICATION_ENV not inititalized");
            }*/

            /* @var $zendApp Zend_Application */
            $zendApp = $bootstrapResource->getApplicationInstance(); //initialize application instance $env


            $zendApp
                ->bootstrap('backCompatibility')
                ->bootstrap('phpEnvoriment')
                ->bootstrap('autoloader')
                ->bootstrap('db');

            $this->_app = $zendApp;

            $this->_initialized = true;
        }
    }

    public function css($fast = false)
    {
        $this->_initialize();

        print 'Building CSS' . PHP_EOL;

        $files = array(
            'styles.less',
        );

        $lessDir = realpath(APPLICATION_PATH . '/../public_source/less');
        $cssDir = PUBLIC_DIR . '/css';
        $bootstrapLessDir = realpath(APPLICATION_PATH . '/../vendor/twitter/bootstrap3/less');

        $cmdTemplate = 'lessc --yui-compress --include-path=.:'.$bootstrapLessDir.' %s > %s';

        foreach ($files as $file) {
            $srcPath = $lessDir . '/' . $file;

            $dstPath = $cssDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.css';

            $cmd = sprintf(
                $cmdTemplate,
                escapeshellarg($srcPath),
                escapeshellarg($dstPath)
            );
            print $cmd . PHP_EOL;
            passthru($cmd);
        }
    }

    public function js($fast = false)
    {
        $this->_initialize();

        print 'Building JS' . PHP_EOL;

        $options = $this->_app->getBootstrap()->getOption('javascript');

        if ($fast) {
            $generateSourceMaps = false;
            $optimize = false;
        } else {
            $generateSourceMaps = isset($options['generateSourceMaps']) && $options['generateSourceMaps'];
            $optimize = true;
        }

        print 'r.js -o ./public_source/build.js' .
                ' generateSourceMaps=' . ($generateSourceMaps ? '1' : 'false') .
                ' preserveLicenseComments=' . (!$generateSourceMaps ? '1' : 'false') .
                ' optimize=' . ($optimize ? 'uglifyjs2' : 'none');

        passthru(
            'r.js -o ./public_source/build.js' .
                ' generateSourceMaps=' . ($generateSourceMaps ? '1' : 'false') .
                ' preserveLicenseComments=' . (!$generateSourceMaps ? '1' : 'false') .
                ' optimize=' . ($optimize ? 'uglify2' : 'none')
        );


    }

    public function all($fast = false)
    {
        $this->css($fast);
        $this->js($fast);
    }

}