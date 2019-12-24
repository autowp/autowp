<?php

namespace Application\Controller\Console;

use Zend\Mvc\Controller\AbstractActionController;
use Application\Model\Brand;

class BuildController extends AbstractActionController
{
    /**
     * @var Brand
     */
    private $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function brandsSpriteAction()
    {
        $dir = 'public_html/img';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $destSprite = $dir . '/brands.png';
        $destCss = $dir . '/brands.css';

        $imageStorage = $this->imageStorage();

        $this->brand->createIconsSprite($imageStorage, $destSprite, $destCss);

        return "done\n";
    }
}
