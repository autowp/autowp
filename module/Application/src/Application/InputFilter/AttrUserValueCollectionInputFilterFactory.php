<?php

namespace Application\InputFilter;

use Application\Service\SpecificationsService;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AttrUserValueCollectionInputFilterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return AttrUserValueCollectionInputFilter
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new AttrUserValueCollectionInputFilter(
            $container->get(SpecificationsService::class)
        );
    }
}
