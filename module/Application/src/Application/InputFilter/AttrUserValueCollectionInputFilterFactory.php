<?php

namespace Application\InputFilter;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AttrUserValueCollectionInputFilterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new AttrUserValueCollectionInputFilter(
            $container->get(\Application\Service\SpecificationsService::class)
        );
    }
}
