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
        $tables = $container->get(\Application\Db\TableManager::class);
        return new PictureModerVoteController(
            $container->get(\Application\HostManager::class),
            $container->get(\Autowp\Message\MessageService::class),
            $container->get('ModerPictureVoteForm2'),
            $container->get(\Application\Model\UserPicture::class),
            $container->get(\Application\Model\PictureModerVote::class),
            $container->get(\Application\Model\Picture::class),
            $tables->get('picture_moder_vote_template')
        );
    }
}
