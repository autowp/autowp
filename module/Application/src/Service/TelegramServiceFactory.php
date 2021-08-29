<?php

declare(strict_types=1);

namespace Application\Service;

use Application\HostManager;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TelegramServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): TelegramService
    {
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new TelegramService(
            $config['telegram'],
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
