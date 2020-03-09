<?php

namespace Application\InputFilter;

use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AttrUserValueCollectionInputFilterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): AttrUserValueCollectionInputFilter
    {
        return new AttrUserValueCollectionInputFilter(
            $container->get(SpecificationsService::class)
        );
    }
}
