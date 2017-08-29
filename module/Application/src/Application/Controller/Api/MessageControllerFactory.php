<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class MessageControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        $filters = $container->get('InputFilterManager');
        return new MessageController(
            $hydrators->get(\Application\Hydrator\Api\MessageHydrator::class),
            $container->get(\Autowp\Message\MessageService::class),
            $filters->get('api_message_list'),
            $filters->get('api_message_post'),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
