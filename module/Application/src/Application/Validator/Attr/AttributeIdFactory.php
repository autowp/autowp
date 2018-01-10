<?php

namespace Application\Validator\Attr;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AttributeIdFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new AttributeId(array_replace(is_array($options) ? $options : [], [
            'table' => $container->get('TableManager')->get('attrs_attributes')
        ]));
    }
}
