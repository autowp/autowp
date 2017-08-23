<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CommentsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new \Application\Comments(
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get('HttpRouter'),
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get('MvcTranslator'),
            $container->get(\Application\Model\Picture::class),
            $tables->get('articles'),
            $tables->get('item'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
