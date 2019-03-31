<?php

namespace Application\Controller\Frontend\Service;

use Application\Model\Brand;
use Application\Model\CarOfDay;
use Application\Model\Categories;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\Twins;
use Application\Service\SpecificationsService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\IndexController as Controller;

class IndexControllerFactory implements FactoryInterface
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
        return new Controller(
            $container->get('fastCache'),
            $container->get(SpecificationsService::class),
            $container->get(CarOfDay::class),
            $container->get(Categories::class),
            $container->get(Perspective::class),
            $container->get(Twins::class),
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(Brand::class),
            $container->get(User::class)
        );
    }
}
