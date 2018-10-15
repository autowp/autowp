<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class RefererFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Referer(
            $container->get('RabbitMQ'),
            $tables->get('referer'),
            $tables->get('referer_whitelist'),
            $tables->get('referer_blacklist')
        );
    }
}
