<?php

namespace Application\Controller\Frontend;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\UsersController as Controller;

class UsersControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Brand::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
