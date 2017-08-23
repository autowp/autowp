<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\CommentController as Controller;

class CommentControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(\Application\Comments::class),
            $container->get('ModerCommentsFilterForm'),
            $hydrators->get(\Application\Hydrator\Api\CommentHydrator::class),
            $tables->get('users')
        );
    }
}
