<?php

namespace Application\View\Helper\Service;

use Application\Model\Picture;
use Application\PictureNameFormatter;
use Application\View\Helper\Pic as Helper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PicFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(PictureNameFormatter::class),
            $container->get(Picture::class)
        );
    }
}
