<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AboutControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new AboutController(
            $container->get(\Zend\Permissions\Acl\Acl::class),
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
