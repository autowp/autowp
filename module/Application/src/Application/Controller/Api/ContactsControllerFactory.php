<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\UserHydrator;
use Application\Model\Contact;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ContactsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return ContactsController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        $filters = $container->get('InputFilterManager');
        $hydrators = $container->get('HydratorManager');

        return new ContactsController(
            $container->get(Contact::class),
            $tables->get('users'),
            $container->get(User::class),
            $filters->get('api_contacts_list'),
            $hydrators->get(UserHydrator::class)
        );
    }
}
