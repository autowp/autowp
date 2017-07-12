<?php

namespace Autowp\Forums;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ForumsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Forums(
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}
