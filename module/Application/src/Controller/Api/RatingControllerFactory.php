<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Comments;
use Application\Hydrator\Api\UserHydrator;
use Application\Model\Item;
use Application\Model\Picture;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class RatingControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): RatingController
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
