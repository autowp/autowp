<?php

namespace Application\Validator\ItemParent;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CatnameNotExistsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new CatnameNotExists(array_merge($options, [
            'antibruteforce' => $container->get(\Application\Model\Antibruteforce::class)
        ]));
    }
}
