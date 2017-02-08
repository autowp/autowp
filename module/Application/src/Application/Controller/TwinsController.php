<?php

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

use Application\Model\DbTable;
use Application\Model\Twins;
use Application\Service\SpecificationsService;

use Autowp\Comments;
use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\TextStorage;

use Zend_Db_Expr;

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

    public function __construct(
        TextStorage\Service $textStorage,
        $cache,
        SpecificationsService $specsService,
        Comments\CommentsService $comments
    ) {

        $this->textStorage = $textStorage;
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->comments = $comments;
    }

    /**
     * @return Twins
     */
    private function getTwins()
    {
        return $this->twins
            ? $this->twins
            : $this->twins = new Twins();
    }

    private function getBrands(array $selectedIds)
    {
        $language = $this->language();

        $key = 'TWINS_SIDEBAR_8_' . $language;

        $arr = $this->cache->getItem($key, $success);
        if (! $success) {
            $arr = $this->getTwins()->getBrands([
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
        $group = $this->getTwins()->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $specs = $this->specsService->specifications($this->getTwins()->getGroupCars($group['id']), [
            'language' => $this->language()
        ]);

        return [
            'group' => $group,
            'specs' => $specs,
        ];
    }

    public function picturesAction()
    {
        $twins = $this->getTwins();

        $group = $twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $select = $twins->getGroupPicturesSelect($group['id'], [
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

        $this->getBrands($twins->getGroupBrandIds($group['id']));

        return [
            'group'        => $group,
            'paginator'    => $paginator,
            'picturesData' => $picturesData
        ];
    }

    public function groupAction()
    {
        $twins = $this->getTwins();

        $group = $twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $carList = $twins->getGroupCars($group['id']);

        $hasSpecs = false;

        foreach ($carList as $car) {
            $hasSpecs = $hasSpecs || $this->specsService->hasSpecs($car->id);
        }

        $picturesCount = $twins->getGroupPicturesCount($group['id']);


        $itemLanguageTable = new DbTable\Item\Language();
        $db = $itemLanguageTable->getAdapter();
        $orderExpr = $db->quoteInto('language = ? desc', $this->language());
        $itemLanguageRows = $itemLanguageTable->fetchAll([
            'item_id = ?' => $group['id']
        ], new \Zend_Db_Expr($orderExpr));

        $textIds = [];
        foreach ($itemLanguageRows as $itemLanguageRow) {
            if ($itemLanguageRow->text_id) {
                $textIds[] = $itemLanguageRow->text_id;
            }
        }

        $description = null;
        if ($textIds) {
            $description = $this->textStorage->getFirstText($textIds);
        }

        $this->getBrands($this->getTwins()->getGroupBrandIds($group['id']));

        return [
            //'name'               => $group->getNameData($this->language()),
            'group'              => $group,
            'description'        => $description,
            'cars'               => $this->car()->listData($carList, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
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
                    'catalogue' => $this->catalogue(),
                    'router'    => $this->getEvent()->getRouter(),
                    'picHelper' => $this->getPluginManager()->get('pic'),
                    'group'     => $group
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
        $pictureTable = new DbTable\Picture();

        $imageStorage = $this->imageStorage();

        $language = $this->language();

        $ids = [];
        foreach ($list as $group) {
            $ids[] = $group->id;
        }

        $picturesCounts = $this->getTwins()->getGroupPicturesCount($ids);

        //TODO: topic stat for authenticated user
        $commentsStats = $this->comments->getTopicStat(
            \Application\Comments::ITEM_TYPE_ID,
            $ids
        );

        $hasSpecs = $this->specsService->twinsGroupsHasSpecs($ids);

        $carLists = [];
        if (count($ids)) {
            $itemTable = new DbTable\Item();

            $db = $itemTable->getAdapter();

            $langJoinExpr = 'item.id = item_language.item_id and ' .
                $db->quoteInto('item_language.language = ?', $language);

            $rows = $db->fetchAll(
                $db->select()
                    ->from('item', [
                        'item.id',
                        'name' => 'if(length(item_language.name), item_language.name, item.name)',
                        'item.body', 'item.begin_model_year', 'item.end_model_year',
                        'item.begin_year', 'item.end_year', 'item.today',
                        'spec' => 'spec.short_name',
                        'spec_full' => 'spec.name',
                    ])
                    ->join('item_parent', 'item.id = item_parent.item_id', 'parent_id')
                    ->joinLeft('item_language', $langJoinExpr, null)
                    ->joinLeft('spec', 'item.spec_id = spec.id', null)
                    ->where('item_parent.parent_id in (?)', $ids)
                    ->order('name')
            );
            foreach ($rows as $row) {
                $carLists[$row['parent_id']][] = $row;
            }
        }

        $groups = [];
        $requests = [];
        foreach ($list as $group) {
            $carList = isset($carLists[$group->id]) ? $carLists[$group->id] : [];

            $picturesShown = 0;
            $cars = [];

            foreach ($carList as $car) {
                $pictureRow = $pictureTable->fetchRow(
                    $pictureTable->select(true)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', (int)$car['id'])
                        ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
                        ->order([
                            new Zend_Db_Expr('picture_item.perspective_id=7 DESC'),
                            new Zend_Db_Expr('picture_item.perspective_id=8 DESC')
                        ])
                        ->limit(1)
                );

                $picture = null;
                if ($pictureRow) {
                    $picturesShown++;

                    $key = 'g' . $group->id . 'p' . $pictureRow->id;

                    $request = $pictureRow->getFormatRequest();
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

            $commentsStat = isset($commentsStats[$group->id]) ? $commentsStats[$group->id] : null;
            $msgCount = $commentsStat ? $commentsStat['messages'] : 0;

            $picturesCount = isset($picturesCounts[$group->id]) ? $picturesCounts[$group->id] : null;

            $groups[] = [
                'name'          => $group->getNameData($language),
                'cars'          => $cars,
                'picturesShown' => $picturesShown,
                'picturesCount' => $picturesCount,
                'hasSpecs'      => isset($hasSpecs[$group->id]) && $hasSpecs[$group->id],
                'msgCount'      => $msgCount,
                'detailsUrl'    => $this->url()->fromRoute('twins/group', [
                    'id' => $group->id
                ]),
                'specsUrl'      => $this->url()->fromRoute('twins/group/specifications', [
                    'id' => $group->id,
                ]),
                'picturesUrl'   => $this->url()->fromRoute('twins/group/pictures', [
                    'id' => $group->id,
                ]),
                'moderUrl'      => $this->url()->fromRoute('moder/cars/params', [
                    'action'  => 'car',
                    'item_id' => $group->id
                ])
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
        $brand = $this->catalogue()->getItemTable()->fetchRow([
            'item_type_id = ?' => DbTable\Item\Type::BRAND,
            'catname = ?'      => (string)$this->params('brand_catname')
        ]);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $canEdit = $this->user()->isAllowed('twins', 'edit');

        $paginator = $this->getTwins()->getGroupsPaginator([
            'brandId' => $brand->id
        ])
            ->setItemCountPerPage(self::GROUPS_PER_PAGE)
            ->setCurrentPageNumber($this->params('page'));

        $groups = $this->prepareList($paginator->getCurrentItems());

        $this->getBrands([$brand->id]);

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

        $paginator = $this->getTwins()->getGroupsPaginator()
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
        $twins = $this->getTwins();

        $group = $twins->getGroup($this->params('id'));
        if (! $group) {
            return $this->notFoundAction();
        }

        $pictureId = (string)$this->params('picture_id');

        $select = $twins->getGroupPicturesSelect($group['id'])
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

            $twins = $this->getTwins();

            $select = $twins->getGroupPicturesSelect($group['id'], [
                'ordering' => $this->catalogue()->picturesOrdering()
            ]);

            $data = $this->pic()->picPageData($picture, $select, [], [
                'paginator' => [
                    'route'     => 'twins/group/pictures/picture',
                    'urlParams' => [
                        'id' => $group['id'],
                    ]
                ]
            ]);

            $this->getBrands($twins->getGroupBrandIds($group['id']));

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

            $select = $this->getTwins()->getGroupPicturesSelect($group['id'], [
                'ordering' => $this->catalogue()->picturesOrdering()
            ]);

            return new JsonModel($this->pic()->gallery2($select, [
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
