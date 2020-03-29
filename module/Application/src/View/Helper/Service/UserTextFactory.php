<?php

namespace Application\View\Helper\Service;

use Application\Model\Picture;
use Application\View\Helper\UserText as Helper;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserTextFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get('Router'),
            $container->get(Picture::class),
            $container->get(User::class)
        );
    }
}
