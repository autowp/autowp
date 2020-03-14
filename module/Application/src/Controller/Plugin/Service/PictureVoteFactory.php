<?php

namespace Application\Controller\Plugin\Service;

use Application\Controller\Plugin\PictureVote as Plugin;
use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureVoteFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Plugin
    {
        $tables = $container->get('TableManager');
        return new Plugin(
            $container->get(PictureModerVote::class),
            $tables->get('picture_moder_vote_template'),
            $container->get(Picture::class),
            $container->get(User::class)
        );
    }
}
