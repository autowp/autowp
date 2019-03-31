<?php

namespace Application\Controller\Api;

use Autowp\TextStorage\Service;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TextControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return TextController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TextController(
            $container->get(Service::class)
        );
    }
}
