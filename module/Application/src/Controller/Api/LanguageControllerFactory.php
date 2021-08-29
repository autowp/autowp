<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class LanguageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): LanguageController
    {
        $config = $container->get('Config');

        return new LanguageController(
            $config['hosts']
        );
    }
}
