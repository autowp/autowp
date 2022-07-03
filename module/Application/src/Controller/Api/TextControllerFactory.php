<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Autowp\TextStorage\Service;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TextControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): TextController
    {
        return new TextController(
            $container->get(Service::class)
        );
    }
}
