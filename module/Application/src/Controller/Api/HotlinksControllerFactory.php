<?php

namespace Application\Controller\Api;

use Application\Model\Referer;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HotlinksControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): HotlinksController
    {
        return new HotlinksController(
            $container->get(Referer::class)
        );
    }
}
