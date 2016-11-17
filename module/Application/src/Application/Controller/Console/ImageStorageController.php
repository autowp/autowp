<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Exception;

class ImageStorageController extends AbstractActionController
{
    public function flushImageAction()
    {
        $imageId = (int)$this->params('image');

        if (!$imageId) {
            throw new \InvalidArgumentException("image id not provided");
        }

        $this->imageStorage()->flush([
            'image' => $imageId
        ]);

        Console::getInstance()->writeLine("done");
    }

    public function clearEmptyDirsAction()
    {
        $dirname = $this->params('dirname');

        Console::getInstance()->writeLine("Clear `$dirname`");
        $dir = $this->imageStorage()->getDir($dirname);
        if (! $dir) {
            throw new Exception("Dir '$dirname' not found");
        }

        $this->recursiveDirectory(realpath($dir->getPath()));

        Console::getInstance()->writeLine("done");
    }

    private function recursiveDirectory($dir)
    {
        $stack[] = $dir;

        while ($stack) {
            $currentDir = array_pop($stack);

            if ($dh = opendir($currentDir)) {
                $count = 0;
                while (($file = readdir($dh)) !== false) {
                    if ($file !== '.' and $file !== '..') {
                        $count++;
                        $currentFile = $currentDir . DIRECTORY_SEPARATOR . $file;
                        if (is_dir($currentFile)) {
                            $stack[] = $currentFile;
                        }
                    }
                }

                if ($count <= 0) {
                    Console::getInstance()->writeLine($currentDir . ' - empty');
                    rmdir($currentDir);
                }
            }
        }
    }
}
