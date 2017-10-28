<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TelegramServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new TelegramService(
            $config['telegram'],
            $container->get('HttpRouter'),
            $container->get(\Application\HostManager::class),
            $container,
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Item::class),
            $tables->get('telegram_brand'),
            $tables->get('telegram_chat'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
