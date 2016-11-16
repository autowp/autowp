<?php

namespace Application\Controller\Console;

use Zend\Console\Console;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand;

class BuildController extends AbstractActionController
{
    public function brandsSpriteAction()
    {
        $destSprite = PUBLIC_DIR . '/img/brands.png';
        $destCss = PUBLIC_DIR . '/css/brands.css';

        $imageStorage = $this->imageStorage();

        $brandModel = new Brand();
        $brandModel->createIconsSprite($imageStorage, $destSprite, $destCss);

        Console::getInstance()->writeLine("done");
    }
}
