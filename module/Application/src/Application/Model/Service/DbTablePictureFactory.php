<?php

namespace Application\Model\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DbTablePictureFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get(\Application\Db\TableManager::class);
        return new \Application\Model\DbTable\Picture([
            'db'           => $container->get(\Zend_Db_Adapter_Abstract::class),
            'imageStorage' => $container->get(\Autowp\Image\Storage::class),
            'perspective'  => $container->get(\Application\Model\Perspective::class),
            'itemTable'    => $tables->get('item')
        ]);
    }
}
