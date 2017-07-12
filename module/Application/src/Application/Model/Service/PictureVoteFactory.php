<?php

namespace Application\Model\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureVoteFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new \Application\Model\PictureVote(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class)
        );
    }
}
