<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UsersServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new UsersService(
            $config['users'],
            $config['hosts'],
            $container->get('MvcTranslator'),
            $container->get(\Zend\Mail\Transport\TransportInterface::class),
            $container->get(\Application\Service\SpecificationsService::class),
            $container->get(\Autowp\Image\Storage::class),
            $container->get(\Autowp\Comments\CommentsService::class),
            $container->get(\Application\Model\UserItemSubscribe::class),
            $container->get(\Application\Model\Contact::class),
            $container->get(\Application\Model\UserAccount::class),
            $container->get(\Application\Model\Picture::class),
            $tables->get('telegram_chat'),
            $container->get(\Autowp\User\Model\User::class),
            $tables->get('log_events_user')
        );
    }
}
