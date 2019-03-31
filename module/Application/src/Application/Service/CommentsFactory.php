<?php

namespace Application\Service;

use Application\Comments;
use Application\HostManager;
use Application\Model\Picture;
use Autowp\Comments\CommentsService;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CommentsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Comments
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Comments(
            $container->get(CommentsService::class),
            $container->get('HttpRouter'),
            $container->get(HostManager::class),
            $container->get(MessageService::class),
            $container->get('MvcTranslator'),
            $container->get(Picture::class),
            $tables->get('articles'),
            $tables->get('item'),
            $container->get(User::class)
        );
    }
}
