<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\Paginator\Paginator;

use Autowp\Comments;
use Autowp\Commons\Paginator\Adapter\Zend1DbTableSelect;
use Autowp\User\Model\DbTable\User;

use Application\ItemNameFormatter;
use Application\Model\Brand;
use Application\Model\ItemParent;
use Application\Model\DbTable;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Service\Mosts;
use Application\Service\SpecificationsService;

use Zend_Db_Table_Select;

class CatalogueController extends AbstractActionController
{
    private $mostsMinCarsCount = 1;

    private $textStorage;

    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var Comments\CommentsService
     */
    private $comments;

    /**
     * @var Item
     */
    private $itemModel;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var TableGateway
     */
    private $itemLinkTable;

    /**
     * @var Mosts
     */
    private $mosts;

    /**
     * @var VehicleType
     */
    private $vehicleType;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    /**
     * @var TableGateway
     */
    private $modificationTable;

    /**
     * @var TableGateway
     */
    private $modificationGroupTable;

    /**
     * @var Brand
     */
    private $brand;

    public function __construct(
        $textStorage,
        $cache,
        SpecificationsService $specsService,
        ItemParent $itemParent,
        ItemNameFormatter $itemNameFormatter,
        $mostsMinCarsCount,
        Comments\CommentsService $comments,
        Item $itemModel,
        Perspective $perspective,
        TableGateway $itemLinkTable,
        Mosts $mosts,
        VehicleType $vehicleType,
        DbTable\Picture $pictureTable,
        TableGateway $modificationTable,
        TableGateway $modificationGroupTable,
        Brand $brand
    ) {

        $this->textStorage = $textStorage;
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->itemParent = $itemParent;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->mostsMinCarsCount = $mostsMinCarsCount;
        $this->comments = $comments;
        $this->itemModel = $itemModel;
        $this->perspective = $perspective;
        $this->itemLinkTable = $itemLinkTable;
        $this->mosts = $mosts;
        $this->vehicleType = $vehicleType;
        $this->pictureTable = $pictureTable;
        $this->modificationTable = $modificationTable;
        $this->modificationGroupTable = $modificationGroupTable;
        $this->brand = $brand;
    }

    private function doBrandAction(callable $callback)
    {
        $language = $this->language();

        $brand = $this->brand->getBrandByCatname($this->params('brand_catname'), $language);

        if (! $brand) {
            return $this->notFoundAction();
        }

        $result = $callback($brand);
        if (is_array($result)) {
            $result = array_replace([
                'brand' => $brand
            ], $result);
        }

        return $result;
    }

    /**
     * @param Zend_Db_Table_Select $select
     * @param string $pictureId
     * @return \Zend_Db_Table_Row_Abstract
     */
    private function fetchSelectPicture(Zend_Db_Table_Select $select, $pictureId)
    {
        $selectRow = clone $select;

        $selectRow->where('pictures.identity = ?', (string)$pictureId);

        return $selectRow->getTable()->fetchRow($selectRow);
    }

    /**
     *
     * @return Zend_Db_Table_Select
     */
    private function selectOrderFromPictures($onlyAccepted = true)
    {
        return $this->selectFromPictures($onlyAccepted)
            ->order($this->catalogue()->picturesOrdering());
    }

    /**
     *
     * @return Zend_Db_Table_Select
     */
    private function selectFromPictures($onlyAccepted = true)
    {
        $select = $this->pictureTable->select(true);

        if ($onlyAccepted) {
            $select->where('pictures.status = ?', Picture::STATUS_ACCEPTED);
        }

        return $select;
    }

    private function carsOrder()
    {
        return $this->catalogue()->itemOrdering();
    }

    private function picturesPaginator(Zend_Db_Table_Select $select, $page)
    {
        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );
        return $paginator
            ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
            ->setCurrentPageNumber($page);
    }

    private function getCarShortName($brand, $carName)
    {
        $shortName = $carName;
        $patterns = [
            preg_quote($brand['name'].'-', '|') => '',
            preg_quote($brand['name'], '|') => '',
            '[[:space:]]+' => ' '
        ];
        foreach ($patterns as $pattern => $replacement) {
            $shortName = preg_replace('|'.$pattern.'|isu', $replacement, $shortName);
        }

        $shortName = trim($shortName);

        return $shortName;
    }

    public function recentAction()
    {
        return $this->doBrandAction(function ($brand) {

            $select = $this->selectFromPictures()
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $brand['id'])
                ->group('pictures.id')
                ->order([
                    'pictures.accept_datetime DESC',
                    'pictures.add_date DESC',
                    'pictures.id DESC'
                ]);

            $paginator = $this->picturesPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4
            ]);

            $this->sidebar()->brand([
                'brand_id' => $brand['id'],
            ]);

            return [
                'paginator'    => $paginator,
                'picturesData' => $picturesData,
            ];
        });
    }

    public function conceptsAction()
    {
        return $this->doBrandAction(function ($brand) {

            $paginator = $this->itemModel->getPaginator([
                'is_concept'         => true,
                'is_concept_inherit' => false,
                'ancestor'           => $brand['id'],
                'order'              => $this->carsOrder()
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $this->sidebar()->brand([
                'brand_id'    => $brand['id'],
                'is_concepts' => true
            ]);

            return [
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'listBuilder' => new \Application\Model\Item\ListBuilder\Catalogue([
                        'catalogue'       => $this->catalogue(),
                        'router'          => $this->getEvent()->getRouter(),
                        'picHelper'       => $this->getPluginManager()->get('pic'),
                        'brand'           => $brand,
                        'specsService'    => $this->specsService
                    ]),
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
                        'type'                 => null,
                        'onlyExactlyPictures'  => false,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ])
                ])
            ];
        });
    }

    public function carsAction()
    {
        return $this->doBrandAction(function ($brand) {

            $cartype = false;
            $catname = $this->params('cartype_catname');
            if ($catname) {
                $cartype = $this->vehicleType->getRowByCatname($catname);
                if (! $cartype) {
                    return $this->notFoundAction();
                }
            }

            $list = $this->vehicleType->getBrandVehicleTypes($brand['id']);
            foreach ($list as &$listItem) {
                $listItem['url'] = $this->url()->fromRoute('catalogue', [
                    'cartype_catname' => $listItem['catname'],
                    'page'            => 1
                ], [], true);
            }
            unset($listItem);

            $paginator = $this->itemModel->getPaginator([
                'is_group'        => false,
                'order'           => $this->carsOrder(),
                'dateful'         => true,
                'ancestor'        => $brand['id'],
                'vehicle_type_id' => $cartype ? $cartype['id'] : null
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            return [
                'cartypes'  => $list,
                'cartype'   => $cartype,
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
                        'type'                 => null,
                        'onlyExactlyPictures'  => false,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new \Application\Model\Item\ListBuilder\Catalogue([
                        'catalogue'       => $this->catalogue(),
                        'router'          => $this->getEvent()->getRouter(),
                        'picHelper'       => $this->getPluginManager()->get('pic'),
                        'brand'           => $brand,
                        'specsService'    => $this->specsService
                    ])
                ])
            ];
        });
    }

    private function getBrandFactories(int $brandId)
    {
        $rows = $this->itemModel->getRows([
            'language' => $this->language(),
            'columns' => [
                'id', 'name', 'cars_count' => new Sql\Expression('count(1)')
            ],
            'item_type_id' => Item::FACTORY,
            'descendant' => [
                'ancestor_or_self' => $brandId
            ],
            'pictures' => [
                'status' => Picture::STATUS_ACCEPTED
            ],
            'order' => 'cars_count desc',
            'limit' => 4
        ]);

        // prefetch
        $requests = [];
        foreach ($rows as $idx => $row) {
            $pictureRow = $this->pictureTable->fetchRow(
                $this->pictureTable->select(true)
                    ->columns([
                        'pictures.id', 'pictures.identity',
                        'pictures.width', 'pictures.height',
                        'pictures.crop_left', 'pictures.crop_top',
                        'pictures.crop_width', 'pictures.crop_height',
                        'pictures.status', 'pictures.image_id'
                    ])
                    ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
                    ->join('picture_item', 'picture_item.picture_id = pictures.id', [])
                    ->where('picture_item.item_id = ?', $row['id'])
                    ->limit(1)
            );

            if ($pictureRow) {
                $requests[$idx] = DbTable\Picture::buildFormatRequest($pictureRow->toArray());
            }
        }

        $imagesInfo = $this->imageStorage()->getFormatedImages($requests, 'picture-thumb');

        $factories = [];
        foreach ($rows as $idx => $row) {
            $factories[] = [
                'name' => $row['name'], // TODO: formatter
                'url'  => $this->url()->fromRoute('factories/factory', [
                    'id' => $row['id']
                ]),
                'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null
            ];
        }

        return $factories;
    }

    public function brandAction()
    {
        return $this->doBrandAction(function ($brand) {

            $language = $this->language();

            $httpsFlag = $this->getRequest()->getUri()->getScheme();

            $key = 'BRAND_'.$brand['id'].'_TOP_PICTURES_7_' . $language . '_' . $httpsFlag;
            $topPictures = $this->cache->getItem($key, $success);
            if (! $success) {
                $select = $this->selectFromPictures(true)
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id = ?', $brand['id'])
                    ->joinLeft('picture_vote_summary', 'pictures.id = picture_vote_summary.picture_id', null)
                    ->order(['picture_vote_summary.positive DESC', 'pictures.add_date DESC', 'pictures.id DESC'])
                    ->group('pictures.id')
                    ->limit(12);

                $db = $select->getAdapter();

                $topPictures = $this->pic()->listData($select, [
                    'width' => 4,
                    'url'   => function ($picture) use ($db, $brand) {

                        $carId = $db->fetchOne(
                            $db->select()
                                ->from('picture_item', 'item_id')
                                ->where('picture_item.picture_id = ?', $picture['id'])
                                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                                ->where('item_parent_cache.parent_id = ?', $brand['id'])
                        );

                        if (! $carId) {
                            return $this->pic()->url($picture['identity']);
                        }

                        $paths = $this->catalogue()->getCataloguePaths($carId, [
                            'toBrand'      => $brand['id'],
                            'breakOnFirst' => true
                        ]);

                        if (count($paths) <= 0) {
                            return $this->pic()->url($picture['identity']);
                        }

                        $path = $paths[0];

                        return $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path'],
                            'picture_id'    => $picture['identity']
                        ]);
                    }
                ]);

                $this->cache->setItem($key, $topPictures);
            }

            $types = [
                'official' => [],
                'helper'   => [],
                'club'     => [],
                'default'  => []
            ];

            foreach ($types as $key => &$type) {
                $type['links'] = $this->itemLinkTable->select([
                    'item_id' => $brand['id'],
                    'type'    => $key
                ]);
            }
            foreach ($types as $key => &$type) {
                if (count($type['links']) <= 0) {
                    unset($types[$key]);
                }
            }

            $haveTwins = $this->itemModel->isExists([
                'item_type_id' => Item::TWINS,
                'descendant_or_self'   => [
                    'ancestor_or_self' => $brand['id']
                ]
            ]);

            $description = $this->itemModel->getTextOfItem($brand['id'], $this->language());

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            $inboxPictures = null;

            if ($this->user()->isAllowed('picture', 'move')) {
                $db = $this->pictureTable->getAdapter();

                $inboxPictures = $db->fetchOne(
                    $db->select()
                        ->from('pictures', 'count(distinct pictures.id)')
                        ->where('pictures.status = ?', Picture::STATUS_INBOX)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $brand['id'])
                );
            }

            $requireAttention = 0;
            $isModerator = $this->user()->inheritsRole('moder');
            if ($isModerator) {
                $requireAttention = $this->getItemModerAttentionCount($brand['id']);
            }

            return [
                'topPictures'      => $topPictures,
                'link_types'       => $types,
                'haveTwins'        => $haveTwins,
                'mostsActive'      => $this->mostsActive($brand['id']),
                'description'      => $description,
                'factories'        => $this->getBrandFactories($brand['id']),
                'inboxPictures'    => $inboxPictures,
                'requireAttention' => $requireAttention
            ];
        });
    }

    /**
     * @param int $brandId
     * @param int $type
     * @return Zend_Db_Table_Select
     */
    private function typePicturesSelect($brandId, $type, $onlyAccepted = true)
    {
        $select = $this->selectOrderFromPictures($onlyAccepted)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->where('picture_item.item_id = ?', $brandId);

        switch ($type) {
            case 'mixed':
                $select->where('picture_item.perspective_id = ?', 25);
                break;
            case 'logo':
                $select->where('picture_item.perspective_id = ?', 22);
                break;
            default:
                $select->where(
                    'picture_item.perspective_id not in (?) or picture_item.perspective_id is null',
                    [22, 25]
                );
                break;
        }

        return $select;
    }

    private function typePictures($type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type);

            $paginator = $this->picturesPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4,
                'url'   => function ($row) {
                    return $this->url()->fromRoute('catalogue', [
                        'action'     => $this->params('action') . '-picture',
                        'picture_id' => $row['identity']
                    ], [], true);
                }
            ]);

            $this->sidebar()->brand([
                'brand_id' => $brand['id'],
                'type'     => $type
            ]);

            return [
                'brand'        => $brand,
                'paginator'    => $paginator,
                'picturesData' => $picturesData
            ];
        });
    }

    public function otherAction()
    {
        return $this->typePictures('unsorted');
    }

    public function mixedAction()
    {
        return $this->typePictures('mixed');
    }

    public function logotypesAction()
    {
        return $this->typePictures('logo');
    }

    private function typePicturesPicture(string $type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type, false);

            return $this->pictureAction($select, function ($select, $picture) use ($brand, $type) {
                $this->sidebar()->brand([
                    'brand_id' => $brand['id'],
                    'type'     => $type
                ]);

                return [
                    'picture'     => array_replace(
                        $this->pic()->picPageData($picture, $select),
                        [
                            'galleryUrl' => $this->url()->fromRoute('catalogue', [
                                'action'  => str_replace('-picture', '-gallery', $this->params('action')),
                                'gallery' => $this->galleryType($picture)
                            ], [], true)
                        ]
                    )
                ];
            });
        });
    }

    public function otherPictureAction()
    {
        return $this->typePicturesPicture('unsorted');
    }

    public function mixedPictureAction()
    {
        return $this->typePicturesPicture('mixed');
    }

    public function logotypesPictureAction()
    {
        return $this->typePicturesPicture('logo');
    }

    private function typePicturesGallery($type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $select = $this->typePicturesSelect($brand['id'], $type, false);

            switch ($this->params('gallery')) {
                case 'inbox':
                    $select->where('pictures.status = ?', Picture::STATUS_INBOX);
                    break;
                case 'removing':
                    $select->where('pictures.status = ?', Picture::STATUS_REMOVING);
                    break;
                default:
                    $select->where('pictures.status = ?', Picture::STATUS_ACCEPTED);
                    break;
            }

            return new JsonModel($this->pic()->gallery2($select, [
                'page'      => $this->params()->fromQuery('page'),
                'pictureId' => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams' => [
                    'action' => str_replace('-gallery', '-picture', $this->params('action'))
                ]
            ]));
        });
    }

    public function otherGalleryAction()
    {
        return $this->typePicturesGallery('unsorted');
    }

    public function mixedGalleryAction()
    {
        return $this->typePicturesGallery('mixed');
    }

    public function logotypesGalleryAction()
    {
        return $this->typePicturesGallery('logo');
    }

    private function stripName($brand, $name)
    {
        $name = explode(' ', $name);
        foreach ($name as $key => $value) {
            if ($brand['name'] == $value) {
                unset($name[$key]);
            }
        }

        return implode(' ', $name);
    }

    private function getCarNames(array $ids)
    {
        if (! count($ids)) {
            return [];
        }

        $language = $this->language();

        $rows = $this->itemModel->getRows([
            'language' => $language,
            'columns'  => ['id', 'name'],
            'id'       => $ids
        ]);

        $result = [];
        foreach ($rows as $row) {
             $result[$row['id']] = $this->itemNameFormatter->format(
                 $row,
                 $language
             );
        }

        return $result;
    }

    private function doBrandItemAction(callable $callback)
    {
        return $this->doBrandAction(function ($brand) use ($callback) {

            $language = $this->language();

            $path = $this->params('path');
            $path = $path ? (array)$path : [];
            $path = array_values($path);

            $parent = [
                'id'           => (int)$brand['id'],
                'link_catname' => (string)$this->params('car_catname'),
                'columns'      => [
                    'brand_item_catname' => 'link_catname'
                ]
            ];

            foreach ($path as $idx => $pathNode) {
                $parent = [
                    'link_catname' => $pathNode,
                    'parent'       => $parent,
                    'columns'      => [
                        'cp_'.$idx.'_item_id' => 'parent_id',
                        'cp_'.$idx.'_name'    => 'link_name'
                    ]
                ];
            }

            $currentCar = $this->itemModel->getRow([
                'language' => $language,
                'columns'  => ['id', 'name', 'is_concept', 'item_type_id', 'is_group', 'produced', 'produced_exactly'],
                'parent'   => $parent
            ]);

            if (! $currentCar) {
                return $this->notFoundAction();
            }

            $topItemId = isset($currentCar['cp_0_item_id']) ? $currentCar['cp_0_item_id'] : $currentCar['id'];

            $carFullName = $this->itemNameFormatter->format(
                $currentCar,
                $language
            );

            // prefetch car names
            $ids = [];
            if (count($path)) {
                $ids[] = $topItemId;
            }
            foreach ($path as $idx => $pathNode) {
                $ridx = count($path) - $idx - 1;
                $idKey = 'cp_'.$ridx.'_item_id';
                $nameKey = 'cp_'.$ridx.'_name';

                if (! $currentCar[$nameKey]) {
                    $ids[] = $currentCar[$idKey];
                }
            }
            $carNames = $this->getCarNames($ids);


            // breadcrumbs
            $breadcrumbs = [];
            $breadcrumbsPath = [];

            $topCarName = null;
            if (count($path)) {
                if (isset($carNames[$topItemId])) {
                    $topCarName = $carNames[$topItemId];
                }
            } else {
                $topCarName = $carFullName;
            }

            $bvName = false;
            $bvName = $this->itemParent->getName($brand['id'], $topItemId, $language);
            if (! $bvName) {
                $bvName = $this->stripName($brand, $topCarName);
            }


            $breadcrumbs[] = [
                'name' => $bvName,
                'url'  => $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand-item',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $currentCar['brand_item_catname'],
                    'path'          => $breadcrumbsPath
                ])
            ];

            foreach ($path as $idx => $pathNode) {
                $ridx = count($path) - $idx - 1;
                $nameKey = 'cp_'.$ridx.'_name';
                $idKey = 'cp_'.$ridx.'_item_id';

                $breadcrumbName = $currentCar[$nameKey];
                if (! $breadcrumbName) {
                    $carId = $currentCar[$idKey];
                    if (isset($carNames[$carId])) {
                        $breadcrumbName = $this->stripName($brand, $carNames[$carId]);
                    }
                }

                $breadcrumbsPath[] = $pathNode;

                $breadcrumbs[] = [
                    'name' => $breadcrumbName,
                    'url'  => $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $currentCar['brand_item_catname'],
                        'path'          => $breadcrumbsPath
                    ])
                ];
            }

            $design = false;

            // new design projects

            $designCarsRow = $this->itemModel->getRow([
                'language'     => $language,
                'columns'      => ['name', 'catname'],
                'item_type_id' => Item::BRAND,
                'child' => [
                    'link_type' => ItemParent::TYPE_DESIGN,
                    'columns'   => [
                        'brand_item_catname' => 'link_catname',
                    ],
                    'descendant' => $currentCar['id']
                ]
            ]);

            if ($designCarsRow) {
                $design = [
                    'name' => $designCarsRow['name'], //TODO: full name via formatter
                    'url'  => $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item',
                        'brand_catname' => $designCarsRow['catname'],
                        'car_catname'   => $designCarsRow['brand_item_catname']
                    ])
                ];
            }

            $this->sidebar()->brand([
                'brand_id'    => $brand['id'],
                'item_id'     => $topItemId,
                'is_concepts' => $currentCar['is_concept']
            ]);

            $result = $callback($currentCar, $breadcrumbs, $brand, $currentCar['brand_item_catname'], $path);

            if (is_array($result)) {
                $result = array_replace([
                    'design'       => $design,
                    'carFullName'  => $carFullName,
                    'carShortName' => $this->getCarShortName($brand, $carFullName),
                    'carCatname'   => $currentCar['brand_item_catname'],
                ], $result);
            }

            return $result;
        });
    }

    private function childsTypeCount(int $carId)
    {
        $pairs = $this->itemParent->getChildItemLinkTypesCount($carId);

        return [
            'stock'  => isset($pairs[ItemParent::TYPE_DEFAULT])
                ? $pairs[ItemParent::TYPE_DEFAULT] : 0,
            'tuning' => isset($pairs[ItemParent::TYPE_TUNING])
                ? $pairs[ItemParent::TYPE_TUNING] : 0,
            'sport'  => isset($pairs[ItemParent::TYPE_SPORT])
                ? $pairs[ItemParent::TYPE_SPORT] : 0
        ];
    }

    public function brandItemAction()
    {
        return $this->doBrandItemAction(function ($currentCar, $breadcrumbs, $brand, $brandItemCatname, $path) {

            $modification = null;
            $modId = (int)$this->params('mod');
            if ($modId) {
                $modification = $this->modificationTable->select(['id' => (int)$modId])->current();
                if (! $modification) {
                    return $this->notFoundAction();
                }
            }

            $modgroupId = (int)$this->params('modgroup');
            if ($modgroupId) {
                $modgroup = $this->modificationGroupTable->select(['id' => (int)$modgroupId])->current();
                if (! $modgroup) {
                    return $this->notFoundAction();
                }
            }

            if ($modgroupId) {
                return $this->brandItemModgroup(
                    $brand,
                    $currentCar,
                    $brandItemCatname,
                    $path,
                    $modgroupId,
                    $modId,
                    $breadcrumbs
                );
            }

            if ($currentCar['is_group']) {
                return $this->brandItemGroup(
                    $brand,
                    $currentCar,
                    $brandItemCatname,
                    $path,
                    $modId,
                    $breadcrumbs
                );
            }

            $type = $this->params('type');
            switch ($type) {
                case 'tuning':
                    $type = ItemParent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = ItemParent::TYPE_SPORT;
                    break;
                default:
                    $type = ItemParent::TYPE_DEFAULT;
                    break;
            }

            $currentCarId = $currentCar['id'];

            $listCars = $this->itemModel->getRows(['id' => (int)$currentCarId]);

            $currentPictures = [];
            $currentPicturesCount = 0;

            $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

            $inboxCount = 0;
            if ($canAcceptPicture) {
                $inboxCount = $this->getCarInboxCount($currentCarId);
            }

            $requireAttention = 0;
            $isModerator = $this->user()->inheritsRole('moder');
            if ($isModerator) {
                $requireAttention = $this->getItemModerAttentionCount($currentCarId);
            }

            $counts = $this->childsTypeCount($currentCarId);

            $texts = $this->itemModel->getTextsOfItem($currentCar['id'], $this->language());

            $currentCar['description'] = $texts['text'];
            $currentCar['text'] = $texts['full_text'];
            $hasHtml = (bool)$currentCar['text'];

            return [
                'car'           => $currentCar,
                'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
                'breadcrumbs'   => $breadcrumbs,
                'type'          => $type,
                'stockCount'    => $counts['stock'],
                'tuningCount'   => $counts['tuning'],
                'sportCount'    => $counts['sport'],
                'picturesCount' => 0,
                'hasHtml'       => $hasHtml,
                'currentPictures'      => $currentPictures,
                'currentPicturesCount' => $currentPicturesCount,
                'currentPicturesUrl'   => $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand-item-pictures',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $brandItemCatname,
                    'path'          => $path,
                    'exact'         => true
                ], [], true),
                'childListData' => $this->car()->listData($listCars, [
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
                        'type'                 => $type == ItemParent::TYPE_DEFAULT ? $type : null,
                        'onlyExactlyPictures'  => true,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new \Application\Model\Item\ListBuilder\CatalogueItem([
                        'catalogue'        => $this->catalogue(),
                        'router'           => $this->getEvent()->getRouter(),
                        'picHelper'        => $this->getPluginManager()->get('pic'),
                        'brand'            => $brand,
                        'specsService'     => $this->specsService,
                        'brandItemCatname' => $brandItemCatname,
                        'itemId'           => $currentCarId,
                        'path'             => $path,
                        'itemParent'       => $this->itemParent
                    ]),
                    'onlyExactlyPictures'  => true,
                    'disableDescription' => true,
                    'disableDetailsLink' => true,
                ]),
                'canAcceptPicture' => $canAcceptPicture,
                'inboxCount'       => $inboxCount,
                'requireAttention' => $requireAttention
            ];
        });
    }

    private function brandItemGroupModifications(int $carId, int $groupId, int $modificationId)
    {
        $db = $this->pictureTable->getAdapter();

        $select = new Sql\Select($this->modificationTable->getTable());
        $select->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
            ->where(['item_parent_cache.item_id' => $carId])
            ->order('modification.name');

        if ($groupId) {
            $select->where(['modification.group_id' => $groupId]);
        } else {
            $select->where(['modification.group_id IS NULL']);
        }

        $modifications = [];
        foreach ($this->modificationTable->selectWith($select) as $mRow) {
            $modifications[] = [
                'name'      => $mRow['name'],
                'url'       => $this->url()->fromRoute('catalogue', [
                    'action' => 'brand-item', // -pictures
                    'mod'    => $mRow['id'],
                ], [], true),
                'count'     => $db->fetchOne(
                    $db->select()
                        ->from('modification_picture', 'count(1)')
                        ->where('modification_picture.modification_id = ?', $mRow['id'])
                        ->join('pictures', 'modification_picture.picture_id = pictures.id', null)
                        ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                        ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                        ->where('item_parent_cache.parent_id = ?', $carId)
                ),
                'active' => $mRow['id'] == $modificationId
            ];
        }

        return $modifications;
    }

    private function brandItemModifications(int $carId, int $modificationId)
    {
        // modifications
        $modificationGroups = [];

        $select = new Sql\Select($this->modificationGroupTable->getTable());

        $select
            ->join('modification', 'modification_group.id = modification.group_id', [])
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
            ->where(['item_parent_cache.item_id' => $carId])
            ->group('modification_group.id')
            ->order('modification_group.name');
        $mgRows = $this->modificationGroupTable->selectWith($select);

        foreach ($mgRows as $mgRow) {
            $modifications = $this->brandItemGroupModifications($carId, $mgRow['id'], $modificationId);

            if ($modifications) {
                $modificationGroups[] = [
                    'name'          => $mgRow['name'],
                    'modifications' => $modifications,
                    'url'           => $this->url()->fromRoute('catalogue', [
                        'action'   => 'brand-item',
                        'modgroup' => $mgRow['id'],
                    ], [], true)
                ];
            }
        }

        $modifications = $this->brandItemGroupModifications($carId, 0, $modificationId);
        if ($modifications) {
            $modificationGroups[] = [
                'name'          => null,
                'modifications' => $modifications
            ];
        }

        return $modificationGroups;
    }

    private function getModgroupPicturesSelect(int $carId, int $modId)
    {
        $db = $this->pictureTable->getAdapter();

        return $db->select()
            ->from(
                $this->pictureTable->info('name'),
                [
                    'id', 'name', 'image_id', 'crop_left', 'crop_top',
                    'crop_width', 'crop_height', 'width', 'height', 'identity'
                ]
            )
            ->where('pictures.status = ?', Picture::STATUS_ACCEPTED)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $carId)
            ->join('modification_picture', 'pictures.id = modification_picture.picture_id', null)
            ->where('modification_picture.modification_id = ?', $modId)
            ->limit(1);
    }

    private function getModgroupPictureList(int $carId, int $modId, array $perspectiveGroupIds)
    {
        $pictures = [];
        $usedIds = [];

        $db = $this->pictureTable->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {
            $select = $this->getModgroupPicturesSelect($carId, $modId)
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    null
                )
                ->where('mp.group_id = ?', $groupId)
                ->order([
                    //'item.is_concept asc',
                    'item_parent_cache.sport asc',
                    'item_parent_cache.tuning asc',
                    'mp.position'
                ])
                ->limit(1);

            /*
            if (isset($options['type'])) {
                switch ($options['type']) {
                    case ItemParent::TYPE_DEFAULT:
                        break;
                    case ItemParent::TYPE_TUNING:
                        $select->where('item_parent_cache.tuning');
                        break;
                    case ItemParent::TYPE_SPORT:
                        $select->where('item_parent_cache.sport');
                        break;
                }
            }
            */

            if ($usedIds) {
                $select
                    ->where('pictures.id not in (?)', $usedIds);
            }


            $picture = $db->fetchRow($select);

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[] = (int)$picture['id'];
            } else {
                $pictures[] = null;
            }
        }

        foreach ($pictures as &$picture) {
            if (! $picture) {
                $select = $this->getModgroupPicturesSelect($carId, $modId)->limit(1)
                    ->order([
                        //'item.is_concept asc',
                        'item_parent_cache.sport asc',
                        'item_parent_cache.tuning asc'
                    ]);
                if ($usedIds) {
                    $select->where('pictures.id not in (?)', $usedIds);
                }

                $picture = $db->fetchRow($select);
                if ($picture) {
                    $usedIds[] = $picture['id'];
                }
            }
        }
        unset($picture);

        $result = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $format = 'picture-thumb';

                $url = $this->pic()->href($picture);

                /*if ($urlCallback) {
                    $url = $urlCallback($car, $picture);
                } else {

                }*/

                $result[] = [
                    'format' => $format,
                    'row'    => $picture,
                    'url'    => $url,
                ];
            } else {
                $result[] = false;
            }
        }

        return $result;
    }

    private function brandItemModgroup(
        $brand,
        $currentCar,
        $brandItemCatname,
        $path,
        int $modgroupId,
        int $modId,
        $breadcrumbs
    ) {
        $currentCarId = $currentCar['id'];

        $imageStorage = $this->imageStorage();
        $catalogue = $this->catalogue();

        $g = $this->perspective->getPageGroupIds(2);

        $select = new Sql\Select($this->modificationTable->getTable());
        $select->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
            ->where([
                'item_parent_cache.item_id' => $currentCarId,
                'modification.group_id'     => $modgroupId
            ])
            ->group('modification.id')
            ->order('modification.name');

        $modifications = [];
        foreach ($this->modificationTable->selectWith($select) as $modification) {
            $pictures = [];

            $pictureRows = $this->getModgroupPictureList($currentCarId, $modification['id'], $g);
            $select = $this->getModgroupPicturesSelect($currentCarId, $modification['id']);
            $pPaginator = new Paginator(
                new Zend1DbTableSelect($select)
            );

            foreach ($pictureRows as $pictureRow) {
                if ($pictureRow) {
                    $request = $catalogue->getPictureFormatRequest($pictureRow['row']);
                    $imageInfo = $imageStorage->getFormatedImage($request, 'picture-thumb');

                    $pictures[] = [
                        'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                        'url'  => $this->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item-picture',
                            'brand_catname' => $brand['catname'],
                            'car_catname'   => $brandItemCatname,
                            'path'          => $path,
                            'exact'         => false,
                            'picture_id'    => $pictureRow['row']['identity']
                        ], [], true)
                    ];
                } else {
                    $pictures[] = false;
                }
            }

            $nameParams = [
                'spec'             => $modification['name'],
                'begin_year'       => $modification['begin_year'],
                'end_year'         => $modification['end_year'],
                'begin_month'      => $modification['begin_month'],
                'end_month'        => $modification['end_month'],
                'begin_model_year' => $modification['begin_model_year'],
                'end_model_year'   => $modification['end_model_year'],
                'today'            => $modification['today']
            ];
            foreach ($nameParams as $key => &$nameParam) {
                if (! isset($nameParam)) {
                    unset($nameParams[$key]);
                }
            }
            unset($nameParam);

            $modifications[] = [
                'nameParams' => $nameParams,
                'name'       => $modification['name'],
                'url'        => $this->url()->fromRoute('catalogue', [
                    'action' => 'brand-item-pictures',
                    'mod'    => $modification['id']
                ], [], true),
                'pictures'      => $pictures,
                'totalPictures' => $pPaginator->getTotalItemCount()
            ];
        }

        $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        $isModerator = $this->user()->inheritsRole('moder');
        if ($isModerator) {
            $requireAttention = $this->getItemModerAttentionCount($currentCarId);
        }

        $texts = $this->itemModel->getTextsOfItem($currentCar['id'], $this->language());

        $currentCar['description'] = $texts['text'];
        $currentCar['text'] = $texts['full_text'];
        $hasHtml = (bool)$currentCar['text'];

        return [
            'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
            'modgroup'         => true,
            'breadcrumbs'      => $breadcrumbs,
            'car'              => (array) $currentCar,
            'modifications'    => $modifications,
            'canAcceptPicture' => $canAcceptPicture,
            'inboxCount'       => $inboxCount,
            'requireAttention' => $requireAttention,
            'hasHtml'          => $hasHtml,
            'isCarModer'       => $this->user()->inheritsRole('cars-moder')
        ];
    }

    private function brandItemGroup(
        $brand,
        $currentCar,
        $brandItemCatname,
        $path,
        int $modId,
        $breadcrumbs
    ) {
        $currentCarId = $currentCar['id'];

        $type = $this->params('type');
        switch ($type) {
            case 'tuning':
                $type = ItemParent::TYPE_TUNING;
                break;
            case 'sport':
                $type = ItemParent::TYPE_SPORT;
                break;
            default:
                $type = [ItemParent::TYPE_DEFAULT, ItemParent::TYPE_DESIGN];
                break;
        }

        $listCars = [];

        $paginator = $this->itemModel->getPaginator([
            'parent' => [
                'id'        => $currentCarId,
                'link_type' => $type
            ],
            'order' => $this->carsOrder()
        ]);

        $paginator
            ->setItemCountPerPage(20)
            ->setCurrentPageNumber($this->params('page'));

        $isLastPage = $paginator->getCurrentPageNumber() == $paginator->count() || ! $paginator->count();

        foreach ($paginator->getCurrentItems() as $row) {
            $listCars[] = $row;
        }

        $currentPictures = [];
        $currentPicturesCount = 0;
        if ($isLastPage && $type == ItemParent::TYPE_DEFAULT) {
            $select = $this->selectOrderFromPictures()
                ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                ->where('picture_item.item_id = ?', $currentCarId);
            $pPaginator = new Paginator(
                new Zend1DbTableSelect($select)
            );
            $pPaginator->setItemCountPerPage(4);

            $imageStorage = $this->imageStorage();
            $language = $this->language();

            $currentPictures = [];

            foreach ($pPaginator->getCurrentItems() as $pictureRow) {
                $imageInfo = $imageStorage->getFormatedImage(
                    $this->pictureTable->getFormatRequest($pictureRow),
                    'picture-thumb'
                );

                $currentPictures[] = [
                    'name' => $this->pic()->name($pictureRow, $language),
                    'src'  => $imageInfo ? $imageInfo->getSrc() : null,
                    'url'  => $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-picture',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandItemCatname,
                        'path'          => $path,
                        'exact'         => true,
                        'picture_id'    => $pictureRow['identity']
                    ], [], true)
                ];
            }

            $currentPicturesCount = $pPaginator->getTotalItemCount();
        }

        $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        $isModerator = $this->user()->inheritsRole('moder');
        if ($isModerator) {
            $requireAttention = $this->getItemModerAttentionCount($currentCarId);
        }

        $ids = [];
        foreach ($listCars as $car) {
            $ids[] = $car['id'];
        }

        $hasChildSpecs = $this->specsService->hasChildSpecs($ids);

        $picturesSelect = $this->selectFromPictures()
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $currentCarId);

        $counts = $this->childsTypeCount($currentCarId);


        $texts = $this->itemModel->getTextsOfItem($currentCar['id'], $this->language());

        $currentCar['description'] = $texts['text'];
        $currentCar['text'] = $texts['full_text'];
        $hasHtml = (bool)$currentCar['text'];

        $otherNames = [];
        foreach ($this->itemModel->getNames($currentCar['id']) as $name) {
            if ($currentCar['name'] != $name) {
                if (! in_array($name, $otherNames)) {
                    $otherNames[] = $name;
                }
            }
        }

        return [
            'car'           => $currentCar,
            'otherNames'    => $otherNames,
            'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
            'paginator'     => $paginator,
            'breadcrumbs'   => $breadcrumbs,
            'type'          => $type,
            'stockCount'    => $counts['stock'],
            'tuningCount'   => $counts['tuning'],
            'sportCount'    => $counts['sport'],
            'picturesCount' => $this->picturesPaginator($picturesSelect, 1)->getTotalItemCount(),
            'hasHtml'       => $hasHtml,
            'currentPictures'      => $currentPictures,
            'currentPicturesCount' => $currentPicturesCount,
            'currentPicturesUrl'   => $this->url()->fromRoute('catalogue', [
                'action'        => 'brand-item-pictures',
                'brand_catname' => $brand['catname'],
                'car_catname'   => $brandItemCatname,
                'path'          => $path,
                'exact'         => true,
                'page'          => null
            ], [], true),
            'childListData' => $this->car()->listData($listCars, [
                'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                    'pictureTable'         => $this->pictureTable,
                    'perspective'          => $this->perspective,
                    'type'                 => $type == ItemParent::TYPE_DEFAULT ? $type : null,
                    'onlyExactlyPictures'  => false,
                    'dateSort'             => false,
                    'disableLargePictures' => false,
                    'perspectivePageId'    => null,
                    'onlyChilds'           => []
                ]),
                'listBuilder' => new \Application\Model\Item\ListBuilder\CatalogueGroupItem([
                    'itemModel'        => $this->itemModel,
                    'catalogue'        => $this->catalogue(),
                    'router'           => $this->getEvent()->getRouter(),
                    'picHelper'        => $this->getPluginManager()->get('pic'),
                    'brand'            => $brand,
                    'specsService'     => $this->specsService,
                    'itemParent'       => $this->itemParent,
                    'brandItemCatname' => $brandItemCatname,
                    'itemId'           => $currentCarId,
                    'path'             => $path,
                    'language'         => $this->language(),
                    'textStorage'      => $this->textStorage,
                    'hasChildSpecs'    => $hasChildSpecs
                ]),
                'disableDescription' => false
            ]),
            'canAcceptPicture' => $canAcceptPicture,
            'inboxCount'       => $inboxCount,
            'requireAttention' => $requireAttention
        ];
    }

    private function getItemModerAttentionCount($carId)
    {
        return $this->comments->getTotalMessagesCount([
            'attention' => Comments\Attention::REQUIRED,
            'type'      => \Application\Comments::PICTURES_TYPE_ID,
            'callback'  => function (\Zend\Db\Sql\Select $select) use ($carId) {
                $select
                    ->join('pictures', 'comment_message.item_id = pictures.id', [])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                    ->where(['item_parent_cache.parent_id = ?' => $carId]);
            }
        ]);
    }

    private function getCarInboxCount(int $carId)
    {
        $select = $this->pictureTable->select(true)
            ->where('pictures.status = ?', Picture::STATUS_INBOX)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
            ->where('item_parent_cache.parent_id = ?', $carId);

        $paginator = new Paginator(
            new Zend1DbTableSelect($select)
        );

        return $paginator->getTotalItemCount();
    }

    /**
     * @param int $carId
     * @param bool $exact
     * @return Zend_Db_Table_Select
     */
    private function getBrandItemPicturesSelect(int $carId, bool $exact, bool $onlyAccepted = true)
    {
        $select = $this->selectFromPictures($onlyAccepted)
            ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
            ->joinLeft('perspectives', 'picture_item.perspective_id = perspectives.id', [])
            ->group(['pictures.id', 'perspectives.position'])
            ->order(array_merge(
                ['perspectives.position'],
                $this->catalogue()->picturesOrdering()
            ));

        if ($exact) {
            $select
                ->where('picture_item.item_id = ?', $carId);
        } else {
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                ->where('item_parent_cache.parent_id = ?', $carId);
        }

        return $select;
    }

    public function brandItemPicturesAction()
    {
        return $this->doBrandItemAction(function ($currentCar, $breadcrumbs, $brand, $brandItemCatname, $path) {

            $exact = (bool)$this->params('exact');

            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact);

            $modification = null;
            $modId = (int)$this->params('mod');
            if ($modId) {
                $modification = $this->modificationTable->select(['id' => (int)$modId])->current();
                if (! $modification) {
                    return $this->notFoundAction();
                }

                $select
                    ->join('modification_picture', 'pictures.id = modification_picture.picture_id', null)
                    ->where('modification_picture.modification_id = ?', $modId);
            }

            $paginator = $this->picturesPaginator($select, $this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

            $picturesData = $this->pic()->listData($select, [
                'width' => 4,
                'url'   => function ($row) use ($brand, $brandItemCatname, $path, $exact) {
                    return $this->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-picture',
                        'brand_catname' => $brand['catname'],
                        'car_catname'   => $brandItemCatname,
                        'path'          => $path,
                        'exact'         => $exact,
                        'picture_id'    => $row['identity']
                    ], [], true);
                }
            ]);

            $counts = $this->childsTypeCount($currentCar['id']);

            return [
                'breadcrumbs'   => $breadcrumbs,
                'picturesData'  => $picturesData,
                'paginator'     => $paginator,
                'stockCount'    => $counts['stock'],
                'tuningCount'   => $counts['tuning'],
                'sportCount'    => $counts['sport'],
                'picturesCount' => $paginator->getTotalItemCount(),
                'type'          => null,
                'modification'  => $modification,
                'modificationGroups' => $this->brandItemModifications($currentCar['id'], $modId),
            ];
        });
    }

    private function pictureAction($select, callable $callback)
    {
        $pictureId = (string)$this->params('picture_id');

        $picture = $this->fetchSelectPicture($select, $pictureId);
        if (! $picture) {
            return $this->notFoundAction();
        }

        $isModer = $this->user()->inheritsRole('moder');

        if ($picture['status'] == Picture::STATUS_REMOVING) {
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if ($isModer || ($user['id'] == $picture['owner_id'])) {
                //$this->getResponse()->setStatusCode(404);
            } else {
                return $this->notFoundAction();
            }

            $select->where('pictures.status = ?', Picture::STATUS_REMOVING);
        } elseif ($picture['status'] == Picture::STATUS_INBOX) {
            $select->where('pictures.status = ?', Picture::STATUS_INBOX);
        } else {
            $select->where('pictures.status = ?', Picture::STATUS_ACCEPTED);
        }

        return $callback($select, $picture);
    }

    private function galleryType($picture)
    {
        if ($picture['status'] == Picture::STATUS_REMOVING) {
            $gallery = 'removing';
        } elseif ($picture['status'] == Picture::STATUS_INBOX) {
            $gallery = 'inbox';
        } else {
            $gallery = null;
        }

        return $gallery;
    }

    public function brandItemPictureAction()
    {
        return $this->doBrandItemAction(function ($currentCar, $breadcrumbs) {
            $exact = (bool)$this->params('exact');

            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact, false);

            return $this->pictureAction($select, function ($select, $picture) use ($breadcrumbs) {
                return [
                    'breadcrumbs' => $breadcrumbs,
                    'picture'     => array_replace(
                        $this->pic()->picPageData($picture, $select),
                        [
                            'galleryUrl' => $this->url()->fromRoute('catalogue', [
                                'action'  => 'brand-item-gallery',
                                'gallery' => $this->galleryType($picture)
                            ], [], true)
                        ]
                    )
                ];
            });
        });
    }

    public function brandItemGalleryAction()
    {
        return $this->doBrandItemAction(function ($currentCar) {

            $exact = (bool)$this->params('exact');
            $select = $this->getBrandItemPicturesSelect($currentCar['id'], $exact, false);

            switch ($this->params('gallery')) {
                case 'inbox':
                    $select->where('pictures.status = ?', Picture::STATUS_INBOX);
                    break;
                case 'removing':
                    $select->where('pictures.status = ?', Picture::STATUS_REMOVING);
                    break;
                default:
                    $select->where('pictures.status = ?', Picture::STATUS_ACCEPTED);
                    break;
            }

            return new JsonModel($this->pic()->gallery2($select, [
                'page'      => $this->params()->fromQuery('page'),
                'pictureId' => $this->params()->fromQuery('pictureId'),
                'reuseParams' => true,
                'urlParams' => [
                    'action' => 'brand-item-picture'
                ]
            ]));
        });
    }

    public function brandItemSpecificationsAction()
    {
        return $this->doBrandItemAction(function ($currentCar, $breadcrumbs) {

            $currentCarId = $currentCar['id'];

            $type = $this->params('type');
            switch ($type) {
                case 'tuning':
                    $type = ItemParent::TYPE_TUNING;
                    break;
                case 'sport':
                    $type = ItemParent::TYPE_SPORT;
                    break;
                default:
                    $type = ItemParent::TYPE_DEFAULT;
                    break;
            }

            $childCars = $this->itemModel->getRows([
                'order'  => $this->carsOrder(),
                'parent' => $currentCar['is_group'] ? [
                    'id'        => $currentCarId,
                    'link_type' => $type
                ] : null,
                'ancestor_or_self' => $currentCar['is_group'] ? null : [
                    'id'       => $currentCarId,
                    'max_diff' => 1
                ]
            ]);

            if (count($childCars) <= 0) {
                $childCars = $this->itemModel->getRows([
                    'order'  => $this->carsOrder(),
                    'parent' => $currentCar['is_group'] ? [
                        'id'        => $currentCarId,
                        'link_type' => $type
                    ] : null,
                    'ancestor_or_self' => $currentCar['is_group'] ? null : $currentCarId
                ]);
            }

            $cars = [];
            foreach ($childCars as $childCar) {
                if ($this->specsService->hasSpecs($childCar['id'])) {
                    $cars[] = $childCar;
                }
            }

            $specs = $this->specsService->specifications($cars, [
                'language'     => $this->language(),
                'contextCarId' => $currentCarId
            ]);

            $ids = [];
            foreach ($cars as $car) {
                $ids[] = $car['id'];
            }

            $contribPairs = $this->specsService->getContributors($ids);

            $userTable = new User();
            $contributors = $userTable->find(array_keys($contribPairs));

            return [
                'breadcrumbs'  => $breadcrumbs,
                'specs'        => $specs,
                'contributors' => $contributors
            ];
        });
    }

    private function mostsActive(int $brandId)
    {
        $carsCount = $this->itemModel->getCount([
            'ancestor' => $brandId
        ]);

        return $carsCount >= $this->mostsMinCarsCount;
    }

    public function brandMostsAction()
    {
        return $this->doBrandAction(function ($brand) {

            if (! $this->mostsActive($brand['id'])) {
                return $this->notFoundAction();
            }

            $language = $this->language();
            $yearsCatname = $this->params('years_catname');
            $carTypeCatname = $this->params('shape_catname');
            $mostCatname = $this->params('most_catname');

            $data = $this->mosts->getData([
                'language' => $language,
                'most'     => $mostCatname,
                'years'    => $yearsCatname,
                'carType'  => $carTypeCatname,
                'brandId'  => $brand['id']
            ]);

            foreach ($data['sidebar']['mosts'] as &$most) {
                $most['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $most['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
            }
            foreach ($data['sidebar']['carTypes'] as &$carType) {
                $carType['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $carType['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
                foreach ($carType['childs'] as &$child) {
                    $child['url'] = $this->url()->fromRoute(
                        'catalogue',
                        array_merge(
                            $child['params'],
                            ['brand_catname' => $brand['catname']]
                        ),
                        [],
                        true
                    );
                }
            }
            foreach ($data['years'] as &$year) {
                $year['url'] = $this->url()->fromRoute(
                    'catalogue',
                    array_merge(
                        $year['params'],
                        ['brand_catname' => $brand['catname']]
                    ),
                    [],
                    true
                );
            }

            // images
            $formatRequests = [];
            $allPictures = [];
            $idx = 0;
            foreach ($data['carList']['cars'] as $car) {
                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $formatRequests[$idx++] = $this->pictureTable->getFormatRequest($picture);
                        $allPictures[] = $picture->toArray();
                    }
                }
            }

            $imageStorage = $this->imageStorage();
            $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

            $names = $this->pictureTable->getNameData($allPictures, [
                'language' => $language
            ]);

            $idx = 0;
            foreach ($data['carList']['cars'] as &$car) {
                $pictures = [];

                $paths = $this->catalogue()->getCataloguePaths($car['car']['id'], [
                    'breakOnFirst' => true
                ]);

                foreach ($car['pictures'] as $picture) {
                    if ($picture) {
                        $id = $picture['id'];

                        $url = null;
                        foreach ($paths as $path) {
                            $url = $this->url()->fromRoute('catalogue', [
                                'action'        => 'brand-item-picture',
                                'brand_catname' => $path['brand_catname'],
                                'car_catname'   => $path['car_catname'],
                                'path'          => $path['path'],
                                'picture_id'    => $picture['identity']
                            ]);
                        }

                        $pictures[] = [
                            'name' => isset($names[$id]) ? $names[$id] : null,
                            'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                            'url'  => $url
                        ];
                        $idx++;
                    } else {
                        $pictures[] = null;
                    }
                }

                $car['name'] = $this->itemModel->getNameData($car['car'], $language);
                $car['pictures'] = $pictures;
            }
            unset($car);

            $sideBarModel = new ViewModel($data);
            $sideBarModel->setTemplate('application/mosts/sidebar');
            $this->layout()->addChild($sideBarModel, 'sidebar');

            return $data;
        });
    }

    public function enginesAction()
    {
        return $this->doBrandAction(function ($brand) {

            $paginator = $this->itemModel->getPaginator([
                'item_type_id' => Item::ENGINE,
                'parent'       => $brand['id'],
                'order'        => $this->carsOrder()
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            $this->sidebar()->brand([
                'brand_id' => $brand['id']
            ]);

            return [
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                        'pictureTable'         => $this->pictureTable,
                        'perspective'          => $this->perspective,
                        'type'                 => null,
                        'onlyExactlyPictures'  => false,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new \Application\Model\Item\ListBuilder\Catalogue([
                        'catalogue'       => $this->catalogue(),
                        'router'          => $this->getEvent()->getRouter(),
                        'picHelper'       => $this->getPluginManager()->get('pic'),
                        'brand'           => $brand,
                        'specsService'    => $this->specsService
                    ])
                ])
            ];
        });
    }
}
