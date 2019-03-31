<?php

namespace Application\Controller\Api;

use Application\Model\Referer;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class HotlinksControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return HotlinksController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new HotlinksController(
            $container->get(Referer::class)
        );
    }
}
