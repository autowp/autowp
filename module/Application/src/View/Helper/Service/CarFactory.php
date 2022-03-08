<?php

declare(strict_types=1);

namespace Application\View\Helper\Service;

use Application\ItemNameFormatter;
use Application\Language;
use Application\View\Helper\Car as Helper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class CarFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(ItemNameFormatter::class),
            $container->get(Language::class)
        );
    }
}
