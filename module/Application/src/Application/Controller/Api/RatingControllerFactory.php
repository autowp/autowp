<?php

namespace Application\Controller\Api;

use Application\Comments;
use Application\Hydrator\Api\UserHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class RatingControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return RatingController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $hydrators = $container->get('HydratorManager');
        return new RatingController(
            $container->get('longCache'),
            $container->get(Comments::class),
            $container->get(Picture::class),
            $container->get(Item::class),
            $container->get(User::class),
            $hydrators->get(UserHydrator::class)
        );
    }
}
