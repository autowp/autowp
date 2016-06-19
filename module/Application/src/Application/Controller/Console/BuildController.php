<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

class BuildController extends AbstractActionController
{
    public function cssAction()
    {
        $console = Console::getInstance();

        $console->writeLine('Building CSS');

        $fast = $this->params('fast');

        $files = [
            'styles.less',
        ];

        $lessDir = realpath(APPLICATION_PATH . '/../public_source/less');
        $cssDir = PUBLIC_DIR . '/css';
        $bootstrapLessDir = realpath(APPLICATION_PATH . '/../vendor/twitter/bootstrap3/less');

        $cmdTemplate = 'lessc --clean-css="--s1 --advanced --compatibility=ie8" --include-path=.:'.$bootstrapLessDir.' %s > %s';

        foreach ($files as $file) {
            $srcPath = $lessDir . '/' . $file;

            $dstPath = $cssDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.css';

            $cmd = sprintf(
                $cmdTemplate,
                escapeshellarg($srcPath),
                escapeshellarg($dstPath)
            );
            $console->writeLine($cmd);
            passthru($cmd);
        }
    }

    public function jsAction()
    {
        $console = Console::getInstance();

        $console->writeLine('Building JS');

        $fast = $this->params('fast');

        if ($fast) {
            $generateSourceMaps = false;
            $optimize = false;
        } else {
            $generateSourceMaps = true;
            $optimize = true;
        }

        $cmd = 'r.js -o ./public_source/build.js' .
                ' generateSourceMaps=' . ($generateSourceMaps ? '1' : 'false') .
                ' preserveLicenseComments=' . (!$generateSourceMaps ? '1' : 'false') .
                ' optimize=' . ($optimize ? 'uglify2' : 'none');

        $console->writeLine($cmd);

        passthru($cmd);
    }

    public function allAction()
    {
        $this->cssAction();
        $this->jsAction();
    }

}