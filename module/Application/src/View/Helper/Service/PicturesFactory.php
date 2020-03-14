<?php

namespace Application\View\Helper\Service;

use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Application\Model\PictureView;
use Application\View\Helper\Pictures as Helper;
use Autowp\Comments\CommentsService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class PicturesFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): Helper
    {
        return new Helper(
            $container->get(CommentsService::class),
            $container->get(PictureView::class),
            $container->get(PictureModerVote::class),
            $container->get(Picture::class)
        );
    }
}
