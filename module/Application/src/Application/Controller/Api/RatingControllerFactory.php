<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class RatingControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new RatingController(
            $container->get('longCache'),
            $container->get(\Application\Comments::class),
            $container->get(\Application\Model\Picture::class),
            $container->get(\Application\Model\Item::class),
            $container->get(\Autowp\User\Model\User::class),
            $hydrators->get(\Application\Hydrator\Api\UserHydrator::class)
        );
    }
}
