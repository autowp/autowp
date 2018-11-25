<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Autowp\Comments;
use Autowp\TextStorage;

use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\Twins;
use Application\Service\SpecificationsService;

class TwinsController extends AbstractActionController
{
    const GROUPS_PER_PAGE = 20;

    /**
     * @var Twins
     */
    private $twins;

    private $textStorage;

    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Picture
     */
    private $picture;

    public function __construct(
        TextStorage\Service $textStorage,
        $cache,
        SpecificationsService $specsService,
        Comments\CommentsService $comments,
        Perspective $perspective,
        Item $itemModel,
        Picture $picture,
        Twins $twins
    ) {

        $this->textStorage = $textStorage;
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->comments = $comments;
        $this->perspective = $perspective;
        $this->itemModel = $itemModel;
        $this->picture = $picture;
        $this->twins = $twins;
    }

    private function getBrands(array $selectedIds)
    {
        $language = $this->language();

        $key = 'TWINS_SIDEBAR_8_' . $language;

        $arr = $this->cache->getItem($key, $success);
        if (! $success) {
            $arr = $this->twins->getBrands([
                'language' => $language
            ]);

            foreach ($arr as &$brand) {
                $brand['url'] = '/ng/twins/' . $brand['catname'];
            }
            unset($brand);

            $this->cache->setItem($key, $arr);
        }

        foreach ($arr as &$brand) {
            $brand['selected'] = in_array($brand['id'], $selectedIds);
        }

        $sideBarModel = new ViewModel([
            'brands' => $arr
        ]);
        $sideBarModel->setTemplate('application/twins/partial/sidebar');
        $this->layout()->addChild($sideBarModel, 'sidebar');
    }

    public function specificationsAction()
    {
        $group = $this->twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $specs = $this->specsService->specifications($this->twins->getGroupCars($group['id']), [
            'language' => $this->language()
        ]);

        return [
            'group' => $group,
            'specs' => $specs,
        ];
    }

    public function picturesAction()
    {
        $group = $this->twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $paginator = $this->picture->getPaginator([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'ancestor_or_self' => $group['id']
            ],
            'order'  => 'resolution_desc'
        ]);

        $paginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($this->params('page'));

            /* @phan-suppress-next-line PhanUndeclaredMethod */
        $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
            'width' => 4,
            'url'   => function ($row) use ($group) {
                return $this->url()->fromRoute('twins/group/pictures/picture', [
                    'id'         => $group['id'],
                    'picture_id' => $row['identity']
                ]);
            }
        ]);

        $this->getBrands($this->twins->getGroupBrandIds($group['id']));

        return [
            'group'        => $group,
            'paginator'    => $paginator,
            'picturesData' => $picturesData
        ];
    }

    public function groupAction()
    {
        $twins = $this->twins;

        $group = $twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $carList = $twins->getGroupCars($group['id']);

        $hasSpecs = false;

        foreach ($carList as $car) {
            $hasSpecs = $hasSpecs || $this->specsService->hasSpecs($car['id']);
        }

        $picturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'ancestor_or_self' => (int)$group['id']
            ]
        ]);


        $description = $this->itemModel->getTextOfItem($group['id'], $this->language());

        $this->getBrands($this->twins->getGroupBrandIds($group['id']));

        return [
            //'name'               => $this->itemModel->getNameData($group, $this->language()),
            'group'              => $group,
            'description'        => $description,
            'cars'               => $this->car()->listData($carList, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'pictureModel'         => $this->picture,
                    'itemModel'            => $this->itemModel,
                    'perspective'          => $this->perspective,
                    'type'                 => null,
                    'onlyExactlyPictures'  => false,
                    'dateSort'             => false,
                    'disableLargePictures' => true,
                    'perspectivePageId'    => null,
                    'onlyChilds'           => []
                ]),
                'disableTwins'         => true,
                'disableSpecs'         => true,
                'listBuilder' => new \Application\Model\Item\ListBuilder\Twins([
                    'catalogue'    => $this->catalogue(),
                    'router'       => $this->getEvent()->getRouter(),
                    'picHelper'    => $this->getPluginManager()->get('pic'),
                    'group'        => $group,
                    'specsService' => $this->specsService
                ])
            ]),
            'picturesCount'      => $picturesCount,
            'hasSpecs'           => $hasSpecs,
            'specsUrl'           => $this->url()->fromRoute('twins/group/specifications', [
                'id' => $group['id']
            ]),
            'picturesUrl'        => $this->url()->fromRoute('twins/group/pictures', [
                'id' => $group['id'],
            ])
        ];
    }

    private function doPictureAction($callback)
    {
        $group = $this->twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $pictureId = (string)$this->params('picture_id');

        $picture = $this->picture->getRow([
            'identity' => $pictureId,
            'status'   => Picture::STATUS_ACCEPTED,
            'item'     => [
                'ancestor_or_self' => $group['id']
            ]
        ]);

        if (! $picture) {
            return $this->notFoundAction();
        }

        return $callback($group, $picture);
    }

    public function pictureAction()
    {
        return $this->doPictureAction(function ($group, $picture) {

            $filter = [
                'order'  => 'resolution_desc',
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'ancestor_or_self' => $group['id']
                ]
            ];

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $data = $this->pic()->picPageData($picture, $filter, [], [
                'paginator' => [
                    'route'     => 'twins/group/pictures/picture',
                    'urlParams' => [
                        'id' => $group['id'],
                    ]
                ]
            ]);

            $this->getBrands($this->twins->getGroupBrandIds($group['id']));

            return array_replace($data, [
                'group'      => $group,
                'galleryUrl' => $this->url()->fromRoute('twins/group/pictures/picture/gallery', [
                    'id'         => $group['id'],
                    'picture_id' => $picture['identity']
                ])
            ]);
        });
    }

    public function pictureGalleryAction()
    {
        return $this->doPictureAction(function ($group, $picture) {

            $filter = [
                'order'  => 'resolution_desc',
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'ancestor_or_self' => $group['id']
                ]
            ];

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            return new JsonModel($this->pic()->gallery2($filter, [
                'page'      => $this->params()->fromQuery('page'),
                'pictureId' => $this->params()->fromQuery('pictureId'),
                'route'     => 'twins/group/pictures/picture',
                'urlParams' => [
                    'action'     => 'picture',
                    'id'         => $group['id'],
                    'picture_id' => $picture['identity']
                ]
            ]));
        });
    }
}
