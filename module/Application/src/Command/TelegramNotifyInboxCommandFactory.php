<?php

declare(strict_types=1);

namespace Application\Command;

use Application\Service\TelegramService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TelegramNotifyInboxCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): TelegramNotifyInboxCommand {
        return new TelegramNotifyInboxCommand(
            'telegram-notify-inbox',
            $container->get(TelegramService::class)
        );
    }
}
