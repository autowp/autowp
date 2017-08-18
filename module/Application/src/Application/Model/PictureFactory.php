<?php

namespace Application\Model;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Picture(
            $tables->get('pictures'),
            $tables->get('item'),
            $container->get(\Application\Model\PictureModerVote::class),
            $tables->get('picture_item'),
            $container->get(\Application\Model\Perspective::class)
        );
    }
}
