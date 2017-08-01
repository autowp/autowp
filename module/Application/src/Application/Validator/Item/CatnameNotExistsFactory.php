<?php

namespace Application\Validator\Item;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CatnameNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new CatnameNotExists(array_replace($options, [
            'item' => $container->get(\Application\Model\Item::class)
        ]));
    }
}
