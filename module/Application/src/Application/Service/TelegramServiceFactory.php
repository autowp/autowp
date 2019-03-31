<?php

namespace Application\Service;

use Application\HostManager;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TelegramServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return TelegramService
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new TelegramService(
            $config['telegram'],
            $container->get('HttpRouter'),
            $container->get(HostManager::class),
            $container,
            $container->get(Picture::class),
            $container->get(Item::class),
            $tables->get('telegram_brand'),
            $tables->get('telegram_chat'),
            $container->get(User::class)
        );
    }
}
