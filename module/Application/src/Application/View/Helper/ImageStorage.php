<?php

namespace Application\View\Helper;

use Zend\View\Helper\AbstractHelper;

use Autowp\Image;

class ImageStorage extends AbstractHelper
{
    private $imageStorage;

    public function __construct(Image\Storage $imageStorage)
    {
        $this->imageStorage = $imageStorage;

        //$this->imageStorage->setForceHttps($this->getController()->getRequest()->isSecure());
    }

    public function __invoke()
    {
        return $this->imageStorage;
    }
}