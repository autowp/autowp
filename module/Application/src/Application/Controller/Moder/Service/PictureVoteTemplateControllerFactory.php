<?php

namespace Application\Controller\Moder\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Moder\PictureVoteTemplateController as Controller;

class PictureVoteTemplateControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get('PictureModerVoteTemplateForm')
        );
    }
}
