<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Model\Item;
use Application\Model\Picture;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class StatControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): StatController
    {
        return new StatController(
            $container->get(Item::class),
            $container->get(Picture::class)
        );
    }
}
