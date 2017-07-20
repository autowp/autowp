<?php

namespace Application\Controller\Plugin\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Plugin\PictureVote as Plugin;

class PictureVoteFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Plugin(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get(\Application\Model\PictureModerVote::class),
            $container->get(\Application\Model\DbTable\Picture::class)
        );
    }
}
