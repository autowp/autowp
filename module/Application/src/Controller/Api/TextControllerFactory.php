<?php

namespace Application\Controller\Api;

use Autowp\TextStorage\Service;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TextControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): TextController
    {
        return new TextController(
            $container->get(Service::class)
        );
    }
}
