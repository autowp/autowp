<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ForumControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new ForumController(
            $container->get(\Autowp\Forums\Forums::class)
        );
    }
}
