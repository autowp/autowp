<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\InfoController as Controller;

class InfoControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(\Autowp\TextStorage\Service::class),
            $tables->get('spec'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
