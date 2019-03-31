<?php

namespace Autowp\Forums;

use Autowp\Comments\CommentsService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ForumsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Forums
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Forums(
            $container->get(CommentsService::class),
            $tables->get('forums_themes'),
            $tables->get('forums_topics'),
            $container->get(User::class)
        );
    }
}
