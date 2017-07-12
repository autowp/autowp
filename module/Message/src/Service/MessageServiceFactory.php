<?php

namespace Autowp\Message\Service;

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
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new MessageService(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get(\Application\Service\TelegramService::class)
        );
    }
}
