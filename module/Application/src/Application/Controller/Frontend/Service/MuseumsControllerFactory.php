<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\MuseumsController as Controller;

class MuseumsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $container->get(\Autowp\TextStorage\Service::class),
            $tables->get('links'),
            $container->get(\Application\Model\Item::class),
            $container->get(\Application\Model\Picture::class)
        );
    }
}
