<?php

namespace Application\Controller\Api;

use Application\Model\Twins;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TwinsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): TwinsController
    {
        return new TwinsController(
            $container->get(Twins::class),
            $container->get('longCache')
        );
    }
}
