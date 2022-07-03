<?php

declare(strict_types=1);

namespace Application\Controller\Frontend\Service;

use Application\Controller\Frontend\YandexController as Controller;
use Application\Model\CarOfDay;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class YandexControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): Controller
    {
        $config = $container->get('Config');
        return new Controller(
            $config['yandex'],
            $container->get(CarOfDay::class)
        );
    }
}
