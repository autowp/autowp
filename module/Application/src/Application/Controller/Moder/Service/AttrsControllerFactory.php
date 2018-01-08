<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\AttrsController as Controller;

class AttrsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Controller(
            $container->get(\Application\Service\SpecificationsService::class),
            $tables->get('attrs_list_options'),
            $tables->get('attrs_types'),
            $tables->get('attrs_attributes')
        );
    }
}
