<?php

declare(strict_types=1);

namespace Application;

return [
    'laminas-cli'     => [
        'commands' => [
            'app:build-brands-sprite'                => Command\BuildBrandsSpriteCommand::class,
            'app:catalogue-refresh-brand-vehicle'    => Command\CatalogueRefreshBrandVehicleCommand::class,
            'app:catalogue-accept-old-unsorted'      => Command\CatalogueAcceptOldUnsortedCommand::class,
            'app:catalogue-rebuild-item-order-cache' => Command\CatalogueRebuildItemOrderCacheCommand::class,
            'app:pictures-df-index'                  => Command\PicturesDfIndexCommand::class,
            'app:pictures-fill-point'                => Command\PicturesFillPointCommand::class,
            'app:pictures-fix-filenames'             => Command\PicturesFixFilenamesCommand::class,
            'app:telegram-notify-inbox'              => Command\TelegramNotifyInboxCommand::class,
            'app:telegram-register'                  => Command\TelegramRegisterCommand::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            Command\BuildBrandsSpriteCommand::class => Command\BuildBrandsSpriteCommandFactory::class,
            Command\CatalogueRefreshBrandVehicleCommand::class
                => Command\CatalogueRefreshBrandVehicleCommandFactory::class,
            Command\CatalogueRebuildItemOrderCacheCommand::class
                => Command\CatalogueRebuildItemOrderCacheCommandFactory::class,
            Command\CatalogueAcceptOldUnsortedCommand::class => Command\CatalogueAcceptOldUnsortedCommandFactory::class,
            Command\PicturesDfIndexCommand::class            => Command\PicturesDfIndexCommandFactory::class,
            Command\PicturesFillPointCommand::class          => Command\PicturesFillPointCommandFactory::class,
            Command\PicturesFixFilenamesCommand::class       => Command\PicturesFixFilenamesCommandFactory::class,
            Command\TelegramNotifyInboxCommand::class        => Command\TelegramNotifyInboxCommandFactory::class,
            Command\TelegramRegisterCommand::class           => Command\TelegramRegisterCommandFactory::class,
        ],
    ],
];
