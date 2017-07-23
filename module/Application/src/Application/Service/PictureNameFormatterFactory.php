<?php

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\PictureNameFormatter as Model;

class PictureNameFormatterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Model(
            $container->get('MvcTranslator'),
            $container->get('ViewRenderer'),
            $container->get(\Application\ItemNameFormatter::class),
            $container->get(\Application\Model\DbTable\Picture::class)
        );
    }
}
