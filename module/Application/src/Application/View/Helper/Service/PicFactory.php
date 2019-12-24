<?php

namespace Application\View\Helper\Service;

use Application\Model\Picture;
use Application\PictureNameFormatter;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\View\Helper\Pic as Helper;

class PicFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Helper
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Helper(
            $container->get(PictureNameFormatter::class),
            $container->get(Picture::class)
        );
    }
}
