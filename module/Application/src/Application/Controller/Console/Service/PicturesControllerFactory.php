<?php

namespace Application\Controller\Console\Service;

use Application\DuplicateFinder;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Console\PicturesController as Controller;

class PicturesControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(Picture::class),
            $container->get(DuplicateFinder::class)
        );
    }
}
