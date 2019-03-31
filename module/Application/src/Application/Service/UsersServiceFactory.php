<?php

namespace Application\Service;

use Application\Model\Contact;
use Application\Model\Picture;
use Application\Model\UserAccount;
use Application\Model\UserItemSubscribe;
use Autowp\Comments\CommentsService;
use Autowp\Image\Storage;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\Mail\Transport\TransportInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UsersServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return UsersService
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $tables = $container->get('TableManager');
        return new UsersService(
            $config['users'],
            $config['hosts'],
            $container->get('MvcTranslator'),
            $container->get(TransportInterface::class),
            $container->get(SpecificationsService::class),
            $container->get(Storage::class),
            $container->get(CommentsService::class),
            $container->get(UserItemSubscribe::class),
            $container->get(Contact::class),
            $container->get(UserAccount::class),
            $container->get(Picture::class),
            $tables->get('telegram_chat'),
            $container->get(User::class),
            $tables->get('log_events_user')
        );
    }
}
