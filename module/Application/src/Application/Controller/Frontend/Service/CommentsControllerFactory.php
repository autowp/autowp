<?php

namespace Application\Controller\Frontend\Service;

use Application\Comments;
use Application\HostManager;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Autowp\Votings\Votings;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CommentsController as Controller;

class CommentsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Controller
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(HostManager::class),
            $container->get('CommentForm'),
            $container->get(MessageService::class),
            $container->get(Comments::class),
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(Votings::class),
            $tables->get('articles'),
            $container->get(User::class)
        );
    }
}
