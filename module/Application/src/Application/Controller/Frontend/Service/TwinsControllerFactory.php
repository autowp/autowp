<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\TwinsController as Controller;

class TwinsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $container->get(\Autowp\TextStorage\Service::class),
            $container->get('longCache'),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\Comments\CommentsService::class),
            $tables->get('perspectives_groups')
        );
    }
}
