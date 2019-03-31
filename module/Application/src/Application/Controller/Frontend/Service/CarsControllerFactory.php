<?php

namespace Application\Controller\Frontend\Service;

use Application\HostManager;
use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\UserItemSubscribe;
use Application\Service\SpecificationsService;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CarsController as Controller;

class CarsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(HostManager::class),
            $container->get(SpecificationsService::class),
            $container->get(MessageService::class),
            $container->get(UserItemSubscribe::class),
            $container->get(Perspective::class),
            $container->get(Item::class),
            $container->get(Picture::class),
            $tables->get('attrs_attributes'),
            $tables->get('attrs_user_values'),
            $container->get(Brand::class),
            $container->get(User::class)
        );
    }
}
