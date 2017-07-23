<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PictureModerVoteTemplateController as Controller;

class PictureModerVoteTemplateControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        $tables = $container->get(\Application\Db\TableManager::class);
        return new Controller(
            $filters->get('api_picture_moder_vote_template_list'),
            $tables->get('picture_moder_vote_template')
        );
    }
}
