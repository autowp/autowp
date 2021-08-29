<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\MessageHydrator;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MessageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MessageController
    {
        $hydrators = $container->get('HydratorManager');
        $filters   = $container->get('InputFilterManager');
        return new MessageController(
            $hydrators->get(MessageHydrator::class),
            $container->get(MessageService::class),
            $filters->get('api_message_list'),
            $filters->get('api_message_post'),
            $container->get(User::class)
        );
    }
}
