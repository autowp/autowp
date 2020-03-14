<?php

namespace Application\Controller\Api;

use Application\Model\Item;
use Application\Model\Picture;
use Autowp\Comments\CommentsService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\Permissions\Acl\Acl;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AboutControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AboutController
    {
        return new AboutController(
            $container->get(Acl::class),
            $container->get(CommentsService::class),
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(User::class)
        );
    }
}
