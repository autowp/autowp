<?php

namespace Application\View\Helper\Service;

use Application\HostManager;
use Application\View\Helper\HostManager as Helper;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HostManagerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(HostManager::class)
        );
    }
}
