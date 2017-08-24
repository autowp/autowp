<?php

namespace Application\Controller\Frontend;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PersonsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new PersonsController(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Picture::class),
            $tables->get('links')
        );
    }
}
