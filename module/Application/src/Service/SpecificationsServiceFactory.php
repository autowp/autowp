<?php

declare(strict_types=1);

namespace Application\Service;

use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SpecificationsServiceFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): SpecificationsService {
        $tables = $container->get('TableManager');
        return new SpecificationsService(
            $container->get('MvcTranslator'),
            $tables->get('attrs_units'),
            $tables->get('attrs_list_options'),
            $tables->get('attrs_attributes'),
            $tables->get('attrs_zone_attributes'),
            $tables->get('attrs_user_values'),
            $tables->get('attrs_values'),
            $tables->get('attrs_values_float'),
            $tables->get('attrs_values_int'),
            $tables->get('attrs_values_list'),
            $tables->get('attrs_values_string'),
            $container->get('RabbitMQ')
        );
    }
}
