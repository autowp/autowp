<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\Service\SpecificationsService;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AttrControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string                $requestedName
     * @param ?array<string, mixed> $options
     */
    public function __invoke(containerinterface $container, $requestedName, ?array $options = null): AttrController
    {
        $filters   = $container->get('InputFilterManager');
        return new AttrController(
            $container->get(SpecificationsService::class),
            $filters->get('api_attr_user_value_patch_query'),
            $filters->get('api_attr_user_value_patch_data'),
        );
    }
}
