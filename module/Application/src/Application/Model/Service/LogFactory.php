<?php

namespace Application\Model\Service;

use Application\Model\Log;
use Application\Model\Picture;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class LogFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Log
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new Log(
            $container->get(Picture::class),
            $tables->get('log_events'),
            $tables->get('log_events_articles'),
            $tables->get('log_events_item'),
            $tables->get('log_events_pictures'),
            $tables->get('log_events_user'),
            $tables->get('item'),
            $container->get(User::class)
        );
    }
}
