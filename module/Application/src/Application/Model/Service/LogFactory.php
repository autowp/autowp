<?php

namespace Application\Model\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LogFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new \Application\Model\Log(
            $container->get(\Application\Model\Picture::class),
            $tables->get('log_events'),
            $tables->get('log_events_articles'),
            $tables->get('log_events_item'),
            $tables->get('log_events_pictures'),
            $tables->get('log_events_user'),
            $tables->get('item'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
