<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Model\Picture;
use Application\PictureNameFormatter;
use Application\Service\Mosts;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MostsControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): MostsController
    {
        $hydrators = $container->get('HydratorManager');
        return new MostsController(
            $container->get(Mosts::class),
            $container->get(Picture::class),
            $hydrators->get(ItemHydrator::class),
            $container->get(PictureNameFormatter::class),
            $container->get(Storage::class)
        );
    }
}
