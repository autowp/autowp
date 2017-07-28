<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Service\TelegramService;

class TelegramServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $tables = $container->get(\Application\Db\TableManager::class);
        return new TelegramService(
            $config['telegram'],
            $container->get('HttpRouter'),
            $container->get(\Application\HostManager::class),
            $container,
            $container->get(\Application\Model\DbTable\Picture::class),
            $tables->get('telegram_brand'),
            $tables->get('telegram_chat')
        );
    }
}
