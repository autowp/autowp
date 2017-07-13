<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\IndexController as Controller;

class IndexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $container->get('fastCache'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Application\Model\CarOfDay::class),
            $container->get(\Application\Model\Categories::class),
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $tables->get('perspectives_groups')
        );
    }
}
