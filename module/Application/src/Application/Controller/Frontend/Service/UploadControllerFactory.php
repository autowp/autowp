<?php

namespace Application\Controller\Frontend\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\UploadController as Controller;

class UploadControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get('ViewHelperManager')->get('partial'),
            $container->get(\Application\Service\TelegramService::class),
            $container->get(\Application\Model\PictureItem::class)
        );
    }
}
