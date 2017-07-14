<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\CategoryController as Controller;

class CategoryControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $container->get('longCache'),
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get(\Application\Model\Categories::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $tables->get('perspectives_groups'),
            $container->get(\Application\Model\Item::class)
        );
    }
}
