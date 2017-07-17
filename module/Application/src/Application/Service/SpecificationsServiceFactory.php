<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Service\SpecificationsService;

class SpecificationsServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new SpecificationsService(
            $container->get('MvcTranslator'),
            $container->get(\Application\ItemNameFormatter::class),
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\ItemParent::class)
        );
    }
}
