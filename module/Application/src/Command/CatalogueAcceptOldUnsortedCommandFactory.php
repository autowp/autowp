<?php

declare(strict_types=1);

namespace Application\Command;

use Application\HostManager;
use Application\Model\Log;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use Application\Service\TelegramService;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CatalogueAcceptOldUnsortedCommandFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param null|array $options
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): CatalogueAcceptOldUnsortedCommand {
        return new CatalogueAcceptOldUnsortedCommand(
            'catalogue-accept-old-unsorted',
            $container->get(HostManager::class),
            $container->get(TelegramService::class),
            $container->get(MessageService::class),
            $container->get(Picture::class),
            $container->get(User::class),
            $container->get('MvcTranslator'),
            $container->get(Log::class),
            $container->get(PictureNameFormatter::class)
        );
    }
}
