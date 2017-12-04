<?php

namespace Application\Factory;

use Interop\Container\ContainerInterface;
use Rollbar\RollbarLogger;
use Zend\ServiceManager\Factory\FactoryInterface;

class RollbarLoggerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');

        return new RollbarLogger($config['rollbar']);
    }
}
