<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

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

        return new UserController(
            $container->get(\Zend\Permissions\Acl\Acl::class),
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class),
            $filters->get('api_user_item'),
            $filters->get('api_user_list'),
            $filters->get('api_user_post'),
            $filters->get('api_user_put'),
            $filters->get('api_user_photo_post'),
            $container->get(\Application\Service\UsersService::class),
            $container->get(\Autowp\User\Model\User::class),
            $config['recaptcha'],
            (bool)getenv('AUTOWP_CAPTCHA'),
            $container->get(\Autowp\User\Model\UserRename::class),
            $config['hosts']
        );
    }
}
