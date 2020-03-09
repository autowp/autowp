<?php

namespace Application\View\Helper\Service;

use Application\View\Helper\Sidebar as Helper;
use Autowp\Message\MessageService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SidebarFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(MessageService::class)
        );
    }
}
