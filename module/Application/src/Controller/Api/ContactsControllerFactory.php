<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\UserHydrator;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContactsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ContactsController
    {
        $filters   = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');

        return new ContactsController(
            $container->get(User::class),
            $filters->get('api_contacts_list'),
            $hydrators->get(UserHydrator::class)
        );
    }
}
