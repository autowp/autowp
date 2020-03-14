<?php

namespace Application\Model\Service;

use Application\Model\UserPicture;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UserPictureFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): UserPicture
    {
        $tables = $container->get('TableManager');
        return new UserPicture(
            $tables->get('pictures'),
            $tables->get('users')
        );
    }
}
