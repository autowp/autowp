<?php

namespace Application\View\Helper\Service;

use Application\Hydrator\Api\UserHydrator;
use Application\MainMenu;
use Application\View\Helper\ApiData as Helper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ApiDataFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        $hydrators = $container->get('HydratorManager');
        return new Helper(
            $hydrators->get(UserHydrator::class),
            $container->get(MainMenu::class)
        );
    }
}
