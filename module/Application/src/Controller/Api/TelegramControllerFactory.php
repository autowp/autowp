<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Controller\Api\TelegramController as Controller;
use Application\Service\TelegramService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TelegramControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        return new Controller(
            $container->get(TelegramService::class)
        );
    }
}
