<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PictureController as Controller;

class PictureControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new Controller(
            $hydrators->get(\Application\Hydrator\Api\PictureHydrator::class),
            $container->get(\Application\Model\PictureItem::class),
            $container->get(\Application\DuplicateFinder::class),
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get(\Application\Model\UserPicture::class),
            $container->get(\Application\Model\Log::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Application\Service\TelegramService::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get(\Application\Model\CarOfDay::class)
        );
    }
}
