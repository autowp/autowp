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
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Plugin(
            $container->get(\Application\Model\PictureModerVote::class),
            $tables->get('picture_moder_vote_template'),
            $container->get(\Application\Model\Picture::class),
            $container->get(\Autowp\User\Model\User::class)
        );
    }
}
