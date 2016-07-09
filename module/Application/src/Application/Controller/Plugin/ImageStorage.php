<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Autowp\Image;

class ImageStorage extends AbstractPlugin
{
    private $imageStorage;

    public function __construct(Image\Storage $imageStorage)
    {
        $this->imageStorage = $imageStorage;
    }

    public function __invoke()
    {
        return $this->imageStorage;
    }
}
