<?php

namespace Application\View\Helper\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

use Application\View\Helper\Comments as Helper;

class CommentsFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Helper(
            $container->get('CommentForm'),
            $container->get(\Autowp\Comments\CommentsService::class)
        );
    }
}
