<?php

namespace Application\Controller\Console;

use Application\Model\Brand;
use Laminas\Mvc\Controller\AbstractActionController;

use function is_dir;
use function mkdir;

class BuildController extends AbstractActionController
{
    private Brand $brand;

    public function __construct(Brand $brand)
    {
        $this->brand = $brand;
    }

    public function brandsSpriteAction(): string
    {
        $dir = 'public_html/img';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $destSprite = $dir . '/brands.png';
        $destCss    = $dir . '/brands.css';

        $imageStorage = $this->imageStorage();

        $this->brand->createIconsSprite($imageStorage, $destSprite, $destCss);

        return "done\n";
    }
}
