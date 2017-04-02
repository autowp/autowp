<?php

namespace Application\Controller\Api\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\Controller\Api\PictureModerVoteTemplateController as Controller;

class PictureModerVoteTemplateControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $filters = $container->get('InputFilterManager');
        return new Controller(
            $container->get(\Zend\Db\Adapter\AdapterInterface::class),
            $filters->get('api_picture_moder_vote_template_list')
        );
    }
}
