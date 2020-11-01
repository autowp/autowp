<?php

namespace Application\Controller\Console;

use Application\Model\Brand;
use Autowp\Image\Storage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class BuildControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): BuildController
    {
        $config = $container->get('Config')['fileStorage'];
        return new BuildController(
            $container->get(Brand::class),
            $config,
            $container->get(Storage::class)
        );
    }
}
