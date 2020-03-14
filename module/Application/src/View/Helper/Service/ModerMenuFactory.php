<?php

namespace Application\View\Helper\Service;

use Application\Model\Picture;
use Application\View\Helper\ModerMenu as Helper;
use Autowp\Comments\CommentsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ModerMenuFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(CommentsService::class),
            $container->get(Picture::class)
        );
    }
}
