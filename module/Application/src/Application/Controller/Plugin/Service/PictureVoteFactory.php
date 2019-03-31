<?php

namespace Application\Controller\Plugin\Service;

use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Plugin\PictureVote as Plugin;

class PictureVoteFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Plugin
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
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
