<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Model\Brand;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class InboxControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): InboxController
    {
        $filters = $container->get('InputFilterManager');
        return new InboxController(
            $container->get(Picture::class),
            $container->get(Brand::class),
            $filters->get('api_inbox_get')
        );
    }
}
