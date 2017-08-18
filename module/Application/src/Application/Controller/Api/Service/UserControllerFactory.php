<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\UserController as Controller;

class UserControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new Controller(
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class),
            $filters->get('api_user_list'),
            $filters->get('api_user_put'),
            $container->get(\Application\Service\UsersService::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
