<?php

declare(strict_types=1);

namespace Application\Service;

use interop\container\containerinterface;
use Laminas\EventManager\EventManager;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CronEventManagerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): EventManager
    {
        return new EventManager();
    }
}
