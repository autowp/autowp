<?php

declare(strict_types=1);

namespace Application\Service;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RabbitMQFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): RabbitMQ
    {
        return new RabbitMQ(
            $container->get('Config')['rabbitmq']
        );
    }
}
