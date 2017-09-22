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
        $config = $container->get('Config');

        return new Controller(
            $container->get(\Zend\Permissions\Acl\Acl::class),
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class),
            $filters->get('api_user_list'),
            $filters->get('api_user_post'),
            $filters->get('api_user_put'),
            $container->get(\Application\Service\UsersService::class),
            $container->get(\Autowp\User\Model\User::class),
            $config['recaptcha'],
            (bool)getenv('AUTOWP_CAPTCHA')
        );
    }
}
