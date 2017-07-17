<?php

namespace Application\Controller\Frontend\Service;

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
            $container->get('longCache'),
            $container->get(\Autowp\Traffic\TrafficControl::class),
            $container->get(\Application\Comments::class),
            $container->get(\Application\Model\Contact::class),
            $container->get(\Autowp\User\Model\UserRename::class),
            $container->get(\Application\Model\Perspective::class),
            $container->get(\Application\Model\UserAccount::class)
        );
    }
}
