<?php

namespace Autowp\Votings;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class VotingsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Votings(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}
