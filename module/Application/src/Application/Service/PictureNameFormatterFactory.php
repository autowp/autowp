<?php

namespace Application\Service;

use Application\ItemNameFormatter;
use Application\Model\Picture;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\PictureNameFormatter as Model;

class PictureNameFormatterFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Model
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Model(
            $container->get('MvcTranslator'),
            $container->get('ViewRenderer'),
            $container->get(ItemNameFormatter::class),
            $container->get(Picture::class)
        );
    }
}
