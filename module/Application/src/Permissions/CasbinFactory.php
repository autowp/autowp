<?php

namespace Application\Permissions;

use Casbin\Enforcer;
use Exception;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CasbinFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     * @throws Exception
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Enforcer
    {
        return new Enforcer(__DIR__ . '/../../config/model.conf', __DIR__ . '/../../config/policy.csv');
    }
}
