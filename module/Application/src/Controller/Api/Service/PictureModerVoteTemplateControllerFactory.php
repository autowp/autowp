<?php

namespace Application\Controller\Api\Service;

use Application\Controller\Api\PictureModerVoteTemplateController as Controller;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PictureModerVoteTemplateControllerFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Controller
    {
        $filters = $container->get('InputFilterManager');
        $tables  = $container->get('TableManager');
        return new Controller(
            $filters->get('api_picture_moder_vote_template_list'),
            $tables->get('picture_moder_vote_template')
        );
    }
}
