<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Zend\ServiceManager\Factory\FactoryInterface;

class RabbitMQFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new RabbitMQ(
            $container->get('Config')['rabbitmq']
        );
    }
}
