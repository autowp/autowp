<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CarsController as Controller;

class CarsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $container->get(\Application\HostManager::class),
            $container->get('AttrsLogFilterForm'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Model\UserItemSubscribe::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Picture::class),
            $tables->get('attrs_attributes'),
            $tables->get('attrs_user_values'),
            $container->get(\Application\Model\Brand::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
