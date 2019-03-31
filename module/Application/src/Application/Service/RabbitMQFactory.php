<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class RabbitMQFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return RabbitMQ
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new RabbitMQ(
            $container->get('Config')['rabbitmq']
        );
    }
}
