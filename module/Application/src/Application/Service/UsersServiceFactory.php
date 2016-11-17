<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Service\UsersService;

class UsersServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        return new UsersService(
            $config['users'],
            $config['hosts'],
            $container->get('MvcTranslator'),
            $container->get(\Zend\Mail\Transport\TransportInterface::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\Image\Storage::class)
        );
    }
}
