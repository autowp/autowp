<?php

namespace Application\Controller\Plugin\Service;

use Application\ItemNameFormatter;
use Application\Model\Brand;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\PictureModerVote;
use Application\Model\PictureView;
use Application\Model\PictureVote;
use Application\Model\UserAccount;
use Application\PictureNameFormatter;
use Application\Service\SpecificationsService;
use Autowp\Comments\CommentsService;
use Autowp\TextStorage\Service;
use Autowp\User\Model\User;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Application\Controller\Plugin\Pic as Plugin;

class PicFactory implements FactoryInterface
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
            $container->get(Service::class),
            $container->get('MvcTranslator'),
            $container->get(PictureNameFormatter::class),
            $container->get(ItemNameFormatter::class),
            $container->get(SpecificationsService::class),
            $container->get(PictureItem::class),
            $container->get('HttpRouter'),
            $container->get(CommentsService::class),
            $container->get(PictureVote::class),
            $container->get(Catalogue::class),
            $container->get(PictureView::class),
            $container->get(Item::class),
            $container->get(Perspective::class),
            $container->get(UserAccount::class),
            $tables->get('links'),
            $container->get(PictureModerVote::class),
            $container->get(Brand::class),
            $container->get(Picture::class),
            $container->get(User::class)
        );
    }
}
