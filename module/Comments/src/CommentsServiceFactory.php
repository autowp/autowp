<?php

namespace Autowp\Comments;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CommentsServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new CommentsService(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}
