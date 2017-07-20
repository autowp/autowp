<?php

namespace Application\Controller\Api;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureModerVoteControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new PictureModerVoteController(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get('ModerPictureVoteForm2'),
            $container->get(\Application\Model\UserPicture::class),
            $container->get(\Application\Model\PictureModerVote::class)
        );
    }
}
