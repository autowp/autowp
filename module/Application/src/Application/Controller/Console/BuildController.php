<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand;

class BuildController extends AbstractActionController
{
    public function brandsSpriteAction()
    {
        $destSprite = 'assets/brandicon/brands.png';
        $destCss = 'assets/brandicon/brands.css';

        $imageStorage = $this->imageStorage();

        $brandModel = new Brand();
        $brandModel->createIconsSprite($imageStorage, $destSprite, $destCss);

        Console::getInstance()->writeLine("done");
    }
}
