<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PictureModerVoteController as Controller;

class PictureModerVoteControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Controller(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get('ModerPictureVoteForm2'),
            $container->get(\Application\Model\UserPicture::class)
        );
    }
}
