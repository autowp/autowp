<?php

namespace Application\View\Helper\Service;

use Application\Model\Picture;
use Application\Model\PictureModerVote;
use Application\Model\PictureView;
use Autowp\Comments\CommentsService;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\View\Helper\Pictures as Helper;

class PicturesFactory implements FactoryInterface
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return Helper
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new Helper(
            $container->get(CommentsService::class),
            $container->get(PictureView::class),
            $container->get(PictureModerVote::class),
            $container->get(Picture::class)
        );
    }
}
