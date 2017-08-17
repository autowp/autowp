<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Autowp\Comments;
use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
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
                $brand['url'] = $this->url()->fromRoute('twins/brand', [
                    'brand_catname' => $brand['catname']
                ]);
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

        $select = $this->twins->getGroupPicturesSelect($group['id'], [
            'ordering' => $this->catalogue()->picturesOrdering()
        ]);

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbTableSelect($select)
        );

        $paginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($this->params('page'));

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $picturesData = $this->pic()->listData($select, [
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

        $picturesCount = $twins->getGroupPicturesCount($group['id']);


        $description = $this->itemModel->getTextOfItem($group['id'], $this->language());

        $this->getBrands($this->twins->getGroupBrandIds($group['id']));

        return [
            //'name'               => $this->itemModel->getNameData($group, $this->language()),
            'group'              => $group,
            'description'        => $description,
            'cars'               => $this->car()->listData($carList, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'pictureTable'         => $this->picture->getPictureTable(),
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

    private function prepareList($list)
    {
        $imageStorage = $this->imageStorage();

        $language = $this->language();

        $ids = [];
        foreach ($list as $group) {
            $ids[] = $group['id'];
        }

        $picturesCounts = $this->twins->getGroupsPicturesCount($ids);

        //TODO: topic stat for authenticated user
        $commentsStats = $this->comments->getTopicStat(
            \Application\Comments::ITEM_TYPE_ID,
            $ids
        );

        $hasSpecs = $this->specsService->twinsGroupsHasSpecs($ids);

        $carLists = [];
        if (count($ids)) {
            $rows = $this->itemModel->getRows([
                'language' => $language,
                'columns' => ['id', 'name'],
                'parent'  => [
                    'id' => $ids,
                    'columns' => ['parent_id']
                ],
                'order' => 'name'
            ]);

            foreach ($rows as $row) {
                $carLists[$row['parent_id']][] = $row;
            }
        }

        $groups = [];
        $requests = [];
        foreach ($list as $group) {
            $carList = isset($carLists[$group['id']]) ? $carLists[$group['id']] : [];

            $picturesShown = 0;
            $cars = [];

            foreach ($carList as $car) {
                $pictureRow = $this->picture->getRow([
                    'status' => Picture::STATUS_ACCEPTED,
                    'item' => [
                        'ancestor_or_self' => (int)$car['id']
                    ],
                    'order' => 'front_angle'
                ]);

                $picture = null;
                if ($pictureRow) {
                    $picturesShown++;

                    $key = 'g' . $group['id']. 'p' . $pictureRow['id'];

                    $request = $this->picture->getFormatRequest($pictureRow);
                    $requests[$key] = $request;

                    $url = $this->url()->fromRoute('twins/group/pictures/picture', [
                        'id'         => $group['id'],
                        'picture_id' => $pictureRow['identity']
                    ]);

                    $picture = [
                        'key' => $key,
                        'url' => $url,
                        'src' => null
                    ];
                }

                $cars[] = [
                    'name'    => $car,
                    'picture' => $picture
                ];
            }

            $commentsStat = isset($commentsStats[$group['id']]) ? $commentsStats[$group['id']] : null;
            $msgCount = $commentsStat ? $commentsStat['messages'] : 0;

            $picturesCount = isset($picturesCounts[$group['id']]) ? $picturesCounts[$group['id']] : null;

            $groups[] = [
                'name'          => $this->itemModel->getNameData($group, $language),
                'cars'          => $cars,
                'picturesShown' => $picturesShown,
                'picturesCount' => $picturesCount,
                'hasSpecs'      => isset($hasSpecs[$group['id']]) && $hasSpecs[$group['id']],
                'msgCount'      => $msgCount,
                'detailsUrl'    => $this->url()->fromRoute('twins/group', [
                    'id' => $group['id']
                ]),
                'specsUrl'      => $this->url()->fromRoute('twins/group/specifications', [
                    'id' => $group['id']
                ]),
                'picturesUrl'   => $this->url()->fromRoute('twins/group/pictures', [
                    'id' => $group['id']
                ]),
                'moderUrl'      => '/ng/moder/items/item/' . $group['id']
            ];
        }


        // fetch images from storage
        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb');

        foreach ($groups as &$group) {
            foreach ($group['cars'] as &$car) {
                if ($car['picture']) {
                    $key = $car['picture']['key'];
                    if (isset($imagesInfo[$key])) {
                        $car['picture']['src'] = $imagesInfo[$key]->getSrc();
                    }
                }
            }
            unset($car);
        }
        unset($group);

        return $groups;
    }

    public function brandAction()
    {
        $brand = $this->itemModel->getTable()->select([
            'item_type_id' => Item::BRAND,
            'catname'      => (string)$this->params('brand_catname')
        ])->current();

        if (! $brand) {
            return $this->notFoundAction();
        }

        $canEdit = $this->user()->isAllowed('twins', 'edit');

        $paginator = $this->twins->getGroupsPaginator($brand['id'])
            ->setItemCountPerPage(self::GROUPS_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $groups = $this->prepareList($paginator->getCurrentItems());

        $this->getBrands([$brand['id']]);

        return [
            'groups'    => $groups,
            'paginator' => $paginator,
            'brand'     => $brand,
            'canEdit'   => $canEdit
        ];
    }

    public function indexAction()
    {
        $canEdit = $this->user()->isAllowed('twins', 'edit');

        $paginator = $this->twins->getGroupsPaginator()
            ->setItemCountPerPage(self::GROUPS_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $groups = $this->prepareList($paginator->getCurrentItems());

        $this->getBrands([]);

        return [
            'groups'    => $groups,
            'paginator' => $paginator,
            'canEdit'   => $canEdit
        ];
    }

    private function doPictureAction($callback)
    {
        $group = $this->twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $pictureId = (string)$this->params('picture_id');

        $select = $this->twins->getGroupPicturesSelect($group['id'])
            ->where('pictures.identity = ?', $pictureId);

        $picture = $select->getTable()->fetchRow($select);

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
