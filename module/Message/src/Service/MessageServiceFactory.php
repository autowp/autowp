<?php

namespace Autowp\Message\Service;

use Application\Service\TelegramService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Autowp\Message\MessageService;

/**
 * @todo Unlink from Telegram
 */
class MessageServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return MessageService
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new MessageService(
            $container->get(TelegramService::class),
            $tables->get('personal_messages'),
            $container->get(User::class)
        );
    }
}
