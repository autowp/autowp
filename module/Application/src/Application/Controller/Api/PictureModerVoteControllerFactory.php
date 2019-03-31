<?php

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PictureModerVoteControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return PictureModerVoteController
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $tables = $container->get('TableManager');
        return new PictureModerVoteController(
            $container->get(HostManager::class),
            $container->get(MessageService::class),
            $container->get('ModerPictureVoteForm2'),
            $container->get(UserPicture::class),
            $container->get(PictureModerVote::class),
            $container->get(Picture::class),
            $tables->get('picture_moder_vote_template'),
            $container->get(User::class)
        );
    }
}
