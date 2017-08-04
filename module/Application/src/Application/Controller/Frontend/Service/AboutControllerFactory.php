<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\AboutController as Controller;

class AboutControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Zend\Permissions\Acl\Acl::class),
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Application\Model\DbTable\Picture::class),
            $container->get(\Application\Model\Item::class)
        );
    }
}
