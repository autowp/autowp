<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;

use Application\Model\Brand;

class BuildController extends AbstractActionController
{
    public function brandsSpriteAction()
    {
        $destSprite = 'public_html/dist/brands.png';
        $destCss = 'public_html/dist/brands.css';

        $imageStorage = $this->imageStorage();

        $brandModel = new Brand();
        $brandModel->createIconsSprite($imageStorage, $destSprite, $destCss);

        return "done\n";
    }

    public function translationsAction()
    {
        $languages = ['en', 'ru', 'zh', 'de', 'fr'];

        foreach ($languages as $language) {
            $srcPath = __DIR__ . '/../../../../language/' . $language . '.php';
            $translations = include $srcPath;

            $json = \Zend\Json\Json::encode($translations);

            $dstPath = __DIR__ . '/../../../../../../assets/languages/' . $language . '.json';

            print $dstPath . PHP_EOL;
            file_put_contents($dstPath, $json);
        }

        return "done\n";
    }
}
