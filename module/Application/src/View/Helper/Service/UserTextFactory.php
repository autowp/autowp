<?php

namespace Application\View\Helper\Service;

use Application\View\Helper\UserText as Helper;
use Autowp\User\Model\User;
use Casbin\Enforcer;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserTextFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(User::class),
            $container->get(Enforcer::class)
        );
    }
}
