<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ContactsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $filters = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');

        return new ContactsController(
            $container->get(\Application\Model\Contact::class),
            $tables->get('users'),
            $container->get(\Autowp\User\Model\User::class),
            $filters->get('api_contacts_list'),
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class)
        );
    }
}
