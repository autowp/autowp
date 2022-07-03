<?php

declare(strict_types=1);

namespace Application\Controller\Api;

use Application\HostManager;
use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Application\Model\UserPicture;
use Autowp\Message\MessageService;
use Autowp\User\Model\User;
use interop\container\containerinterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureModerVoteControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(
        containerinterface $container,
        $requestedName,
        ?array $options = null
    ): PictureModerVoteController {
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
