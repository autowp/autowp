<?php

namespace Application\Controller;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\Paginator\Paginator;
use Zend\Router\Http\TreeRouteStack;

use Autowp\Comments;
use Autowp\User\Model\User;

use Application\ItemNameFormatter;
use Application\Model\Brand;
use Application\Model\ItemParent;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\VehicleType;
use Application\Service\Mosts;
use Application\Service\SpecificationsService;

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
     * @var Picture
     */
    private $picture;

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

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var TreeRouteStack
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;

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
        Picture $picture,
        TableGateway $modificationTable,
        TableGateway $modificationGroupTable,
        Brand $brand,
        User $userModel,
        TreeRouteStack $router,
        TranslatorInterface $translator
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
        $this->picture = $picture;
        $this->modificationTable = $modificationTable;
        $this->modificationGroupTable = $modificationGroupTable;
        $this->brand = $brand;
        $this->userModel = $userModel;
        $this->router = $router;
        $this->translator = $translator;
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

            $paginator = $this->picture->getPaginator([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'ancestor_or_self' => $brand['id']
                ],
                'order'  => 'accept_datetime_desc'
            ]);

            $paginator
                ->setItemCountPerPage(24)
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
                'width' => 6
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
                'order'              => $this->catalogue()->itemOrdering()
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            return [
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'listBuilder' => new Item\ListBuilder\Catalogue([
                        'catalogue'       => $this->catalogue(),
                        'router'          => $this->getEvent()->getRouter(),
                        'picHelper'       => $this->getPluginManager()->get('pic'),
                        'brand'           => $brand,
                        'specsService'    => $this->specsService
                    ]),
                    'pictureFetcher' => new Item\PerspectivePictureFetcher([
                        'pictureModel'         => $this->picture,
                        'itemModel'            => $this->itemModel,
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
                'item_type_id'    => Item::VEHICLE,
                'is_group'        => false,
                'order'           => $this->catalogue()->itemOrdering(),
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

            return [
                'cartypes'  => $list,
                'cartype'   => $cartype,
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'pictureFetcher' => new Item\PerspectivePictureFetcher([
                        'pictureModel'         => $this->picture,
                        'itemModel'            => $this->itemModel,
                        'perspective'          => $this->perspective,
                        'type'                 => null,
                        'onlyExactlyPictures'  => false,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new Item\ListBuilder\Catalogue([
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

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
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
            $pictureRow = $this->picture->getRow([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => $row['id']
            ]);

            if ($pictureRow) {
                $requests[$idx] = $pictureRow['image_id'];
            }
        }

        $imagesInfo = $this->imageStorage()->getFormatedImages($requests, 'picture-thumb-medium');

        $factories = [];
        foreach ($rows as $idx => $row) {
            $factories[] = [
                'name' => $row['name'], // TODO: formatter
                'url'  => '/ng/factories/' . $row['id'],
                'src'  => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null
            ];
        }

        return $factories;
    }

    public function brandAction()
    {
        return $this->doBrandAction(function ($brand) {

            $language = $this->language();

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $httpsFlag = $this->getRequest()->getUri()->getScheme();

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $isModer = $this->user()->inheritsRole('pictures-moder');

            $key = 'BRAND_'.$brand['id'].'_TOP_PICTURES_10_' . $language . '_' . $httpsFlag . '_' . (int)$isModer;
            $topPictures = $this->cache->getItem($key, $success);
            if (! $success) {
                $pictureRows = $this->picture->getRows([
                    'status' => Picture::STATUS_ACCEPTED,
                    'item'   => [
                        'ancestor_or_self' => $brand['id']
                    ],
                    'order'  => 'likes',
                    'limit'  => 12
                ]);

                /* @phan-suppress-next-line PhanUndeclaredMethod */
                $topPictures = $this->pic()->listData($pictureRows, [
                    'width' => 4,
                    'url'   => function ($picture) use ($brand) {

                        $row = $this->itemModel->getRow([
                            'ancestor_or_self' => $brand['id'],
                            'pictures'         => [
                                'id' => $picture['id']
                            ]
                        ]);

                        if (! $row) {
                            /* @phan-suppress-next-line PhanUndeclaredMethod */
                            return $this->pic()->url($picture['identity']);
                        }

                        $paths = $this->catalogue()->getCataloguePaths($row['id'], [
                            'toBrand'      => $brand['id'],
                            'breakOnFirst' => true
                        ]);

                        if (count($paths) <= 0 || $paths[0]['type'] != 'brand-item') {
                            /* @phan-suppress-next-line PhanUndeclaredMethod */
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

            $inboxPictures = null;

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            if ($this->user()->isAllowed('picture', 'move')) {
                $inboxPictures = $this->picture->getCountDistinct([
                    'status' => Picture::STATUS_INBOX,
                    'item'   => [
                        'ancestor_or_self' => $brand['id']
                    ]
                ]);
            }

            $requireAttention = 0;
            /* @phan-suppress-next-line PhanUndeclaredMethod */
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
                'requireAttention' => $requireAttention,
                'sections'         => $this->brandSections($language, $brand['id'], $brand['catname'])
            ];
        });
    }

    private function brandSections(
        string $language,
        int $brandId,
        string $brandCatname
    ) {
        // create groups array
        $sections = $this->carSections($language, $brandId, $brandCatname, true);

        $sections = array_merge(
            $sections,
            [
                [
                    'name'   => 'Other',
                    'url'    => null,
                    'groups' => $this->otherGroups(
                        $brandId,
                        $brandCatname,
                        true
                    )
                ]
            ]
        );

        return $sections;
    }

    private function otherGroups(
        int $brandId,
        string $brandCatname,
        bool $conceptsSeparatly
    ) {

        $groups = [];

        if ($conceptsSeparatly) {
            // concepts
            $hasConcepts = $this->itemModel->isExists([
                'ancestor'   => $brandId,
                'is_concept' => true
            ]);

            if ($hasConcepts) {
                $groups['concepts'] = [
                    'url' => $this->url()->fromRoute('catalogue', [
                        'action'        => 'concepts',
                        'brand_catname' => $brandCatname
                    ]),
                    'name' => $this->translator->translate('concepts and prototypes'),
                ];
            }
        }

        // logotypes
        $logoPicturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'          => $brandId,
                'perspective' => 22
            ],
        ]);

        if ($logoPicturesCount > 0) {
            $groups['logo'] = [
                'url' => $this->url()->fromRoute('catalogue', [
                    'action'        => 'logotypes',
                    'brand_catname' => $brandCatname
                ]),
                'name'  => $this->translator->translate('logotypes'),
                'count' => $logoPicturesCount
            ];
        }

        // mixed
        $mixedPicturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'          => $brandId,
                'perspective' => 25
            ],
        ]);
        if ($mixedPicturesCount > 0) {
            $groups['mixed'] = [
                'url' => $this->url()->fromRoute('catalogue', [
                    'action' => 'mixed',
                    'brand_catname' => $brandCatname
                ]),
                'name'  => $this->translator->translate('mixed'),
                'count' => $mixedPicturesCount
            ];
        }

        // unsorted
        $unsortedPicturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'id'                  => $brandId,
                'perspective_exclude' => [22, 25]
            ],
        ]);
        if ($unsortedPicturesCount > 0) {
            $groups['unsorted'] = [
                'url'     => $this->url()->fromRoute('catalogue', [
                    'action'        => 'other',
                    'brand_catname' => $brandCatname
                ]),
                'name'  => $this->translator->translate('unsorted'),
                'count' => $unsortedPicturesCount
            ];
        }

        return array_values($groups);
    }

    private function carSections(
        string $language,
        int $brandId,
        string $brandCatname,
        bool $conceptsSeparatly
    ) {
        $sectionsPresets = [
            'other' => [
                'name'         => null,
                'car_type_id'  => null,
                'item_type_id' => Item::VEHICLE
            ],
            'moto' => [
                'name'        => 'catalogue/section/moto',
                'car_type_id' => 43,
                'item_type_id' => Item::VEHICLE
            ],
            'bus' => [
                'name' => 'catalogue/section/buses',
                'car_type_id' => 19,
                'item_type_id' => Item::VEHICLE
            ],
            'truck' => [
                'name' => 'catalogue/section/trucks',
                'car_type_id' => 17,
                'item_type_id' => Item::VEHICLE
            ],
            'tractor' => [
                'name'        => 'catalogue/section/tractors',
                'car_type_id' => 44,
                'item_type_id' => Item::VEHICLE
            ],
            'engine' => [
                'name'        => 'catalogue/section/engines',
                'car_type_id' => null,
                'item_type_id' => Item::ENGINE,
                'url'          => $this->router->assemble([
                    'brand_catname' => $brandCatname,
                    'action'        => 'engines'
                ], [
                    'name' => 'catalogue'
                ])
            ]
        ];

        $sections = [];
        foreach ($sectionsPresets as $sectionsPreset) {
            $sectionGroups = $this->carSectionGroups(
                $language,
                $brandId,
                $brandCatname,
                $sectionsPreset,
                $conceptsSeparatly
            );

            usort($sectionGroups, function ($a, $b) {
                return strnatcasecmp($a['name'], $b['name']);
            });

            $sections[] = [
                'name'   => $sectionsPreset['name'],
                'url'    => isset($sectionsPreset['url']) ? $sectionsPreset['url'] : null,
                'groups' => $sectionGroups
            ];
        }

        return $sections;
    }

    private function carSectionGroups(
        string $language,
        int $brandId,
        string $brandCatname,
        array $section,
        bool $conceptsSeparatly
    ) {
        if ($section['car_type_id']) {
            $select = $this->carSectionGroupsSelect(
                $brandId,
                $section['item_type_id'],
                $section['car_type_id'],
                null,
                $conceptsSeparatly
            );
            $rows = $this->itemModel->getTable()->selectWith($select);
        } else {
            $rows = [];
            $select = $this->carSectionGroupsSelect(
                $brandId,
                $section['item_type_id'],
                0,
                false,
                $conceptsSeparatly
            );
            foreach ($this->itemModel->getTable()->selectWith($select) as $row) {
                $rows[$row['item_id']] = $row;
            }
            $select = $this->carSectionGroupsSelect(
                $brandId,
                $section['item_type_id'],
                0,
                true,
                $conceptsSeparatly
            );
            foreach ($this->itemModel->getTable()->selectWith($select) as $row) {
                $rows[$row['item_id']] = $row;
            }
        }

        $groups = [];
        foreach ($rows as $brandItemRow) {
            $url = $this->url()->fromRoute('catalogue', [
                'action'        => 'brand-item',
                'brand_catname' => $brandCatname,
                'car_catname'   => $brandItemRow['brand_item_catname']
            ]);

            $name = $this->itemModel->getName($brandItemRow['item_id'], $language);

            $groups[] = [
                'item_id' => $brandItemRow['item_id'],
                'url'     => $url,
                'name'    => $name,
            ];
        }

        return $groups;
    }

    private function carSectionGroupsSelect(
        int $brandId,
        int $itemTypeId,
        int $carTypeId,
        $nullType,
        bool $conceptsSeparatly
    ): Sql\Select {
        $select = new Sql\Select($this->itemModel->getTable()->getTable());
        $select
            ->columns([
                'item_id'  => 'id',
                'car_name' => 'name',
            ])
            ->join('item_parent', 'item.id = item_parent.item_id', [
                'brand_item_catname' => 'catname',
                'brand_id' => 'parent_id'
            ])
            ->where(['item_parent.parent_id' => $brandId])
            ->group('item.id');

        if ($conceptsSeparatly) {
            $select->where(['NOT item.is_concept']);
        }

        if ($itemTypeId != Item::VEHICLE) {
            $select->where(['item.item_type_id' => $itemTypeId]);

            return $select;
        }

        $select->where([
            new Sql\Predicate\In('item.item_type_id', [Item::VEHICLE, Item::BRAND])
        ]);
        if ($carTypeId) {
            $select
                ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', [])
                ->join('car_types_parents', 'vehicle_vehicle_type.vehicle_type_id = car_types_parents.id', [])
                ->where(['car_types_parents.parent_id' => $carTypeId]);

            return $select;
        }

        if ($nullType) {
            $select
                ->join(
                    'vehicle_vehicle_type',
                    'item.id = vehicle_vehicle_type.vehicle_id',
                    [],
                    $select::JOIN_LEFT
                )
                ->where(['vehicle_vehicle_type.vehicle_id is null']);

            return $select;
        }

        $otherTypesIds = $this->vehicleType->getDescendantsAndSelfIds([43, 44, 17, 19]);

        $select->join(
            'vehicle_vehicle_type',
            'item.id = vehicle_vehicle_type.vehicle_id',
            []
        );

        if ($otherTypesIds) {
            $select->where([
                new Sql\Predicate\NotIn(
                    'vehicle_vehicle_type.vehicle_type_id',
                    $otherTypesIds
                )
            ]);
        }

        return $select;
    }

    private function typePicturesFilter(int $brandId, string $type): array
    {
        $filter = [
            'item'  => [
                'id' => $brandId,
            ],
            'order' => 'resolution_desc'
        ];

        switch ($type) {
            case 'mixed':
                $filter['item']['perspective'] = 25;
                break;
            case 'logo':
                $filter['item']['perspective'] = 22;
                break;
            default:
                $filter['item']['perspective_exclude'] = [22, 25];
                break;
        }

        return $filter;
    }

    private function typePictures($type)
    {
        return $this->doBrandAction(function ($brand) use ($type) {

            $filter = $this->typePicturesFilter($brand['id'], $type);
            $filter['status'] = Picture::STATUS_ACCEPTED;

            $paginator = $this->picture->getPaginator($filter);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
                'width' => 4,
                'url'   => function ($row) {
                    return $this->url()->fromRoute('catalogue', [
                        'action'     => $this->params('action') . '-picture',
                        'picture_id' => $row['identity']
                    ], [], true);
                }
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

            $filter = $this->typePicturesFilter($brand['id'], $type);

            return $this->pictureAction($filter, function (array $filter, $picture) use ($brand, $type) {
                return [
                    'picture'     => array_replace(
                        /* @phan-suppress-next-line PhanUndeclaredMethod */
                        $this->pic()->picPageData($picture, $filter),
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

            $filter = $this->typePicturesFilter($brand['id'], $type);

            switch ($this->params('gallery')) {
                case 'inbox':
                    $filter['status'] = Picture::STATUS_INBOX;
                    break;
                case 'removing':
                    $filter['status'] = Picture::STATUS_REMOVING;
                    break;
                default:
                    $filter['status'] = Picture::STATUS_ACCEPTED;
                    break;
            }

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            return new JsonModel($this->pic()->gallery2($filter, [
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
                $nameKey = 'cp_'.$idx.'_name';
                $idKey = 'cp_'.$idx.'_item_id';

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

            $design = $this->itemModel->getDesignInfo($this->router, $currentCar['id'], $language);

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

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

            $inboxCount = 0;
            if ($canAcceptPicture) {
                $inboxCount = $this->getCarInboxCount($currentCarId);
            }

            $requireAttention = 0;
            /* @phan-suppress-next-line PhanUndeclaredMethod */
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
                    'pictureFetcher' => new Item\PerspectivePictureFetcher([
                        'pictureModel'         => $this->picture,
                        'itemModel'            => $this->itemModel,
                        'perspective'          => $this->perspective,
                        'type'                 => $type == ItemParent::TYPE_DEFAULT ? $type : null,
                        'onlyExactlyPictures'  => true,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new Item\ListBuilder\CatalogueItem([
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
            $count = $this->picture->getCount([
                'item' => [
                    'ancestor_or_self' => $carId
                ],
                'modification' => $mRow['id']
            ]);

            $modifications[] = [
                'name'      => $mRow['name'],
                'url'       => $this->url()->fromRoute('catalogue', [
                    'action' => 'brand-item', // -pictures
                    'mod'    => $mRow['id'],
                ], [], true),
                'count'     => $count,
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

    private function getModgroupPicturesSelect(int $carId, int $modId): Sql\Select
    {
        $select = $this->picture->getTable()->getSql()->select();

        return $select->columns(
            [
                    'id', 'name', 'image_id', 'width', 'height', 'identity'
                ]
        )
            ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
            ->join('modification_picture', 'pictures.id = modification_picture.picture_id', [])
            ->where([
                'pictures.status'                      => Picture::STATUS_ACCEPTED,
                'item_parent_cache.parent_id'          => $carId,
                'modification_picture.modification_id' => $modId
            ])
            ->limit(1);
    }

    /**
     * @suppress PhanUndeclaredMethod
     */
    private function getModgroupPictureList(int $carId, int $modId, array $perspectiveGroupIds)
    {
        $pictures = [];
        $usedIds = [];

        foreach ($perspectiveGroupIds as $groupId) {
            $select = $this->getModgroupPicturesSelect($carId, $modId)
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    []
                )
                ->where(['mp.group_id' => $groupId])
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
                $select->where([new Sql\Predicate\NotIn('pictures.id', $usedIds)]);
            }

            $picture = $this->picture->getTable()->selectWith($select)->current();

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
                    $select->where([new Sql\Predicate\NotIn('pictures.id', $usedIds)]);
                }

                $picture = $this->picture->getTable()->selectWith($select)->current();
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

                /* @phan-suppress-next-line PhanUndeclaredMethod */
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
                new \Zend\Paginator\Adapter\DbSelect($select, $this->picture->getTable()->getAdapter())
            );

            foreach ($pictureRows as $pictureRow) {
                if ($pictureRow) {
                    $imageInfo = null;
                    if ($pictureRow['image_id']) {
                        $imageStorage->getFormatedImage($pictureRow['image_id'], 'picture-thumb');
                    }

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
                'begin_model_year_fraction' => $modification['begin_model_year_fraction'],
                'end_model_year_fraction'   => $modification['end_model_year_fraction'],
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        /* @phan-suppress-next-line PhanUndeclaredMethod */
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
            'isCarModer'       => $this->user()->inheritsRole('cars-moder') // @phan-suppress-current-line PhanUndeclaredMethod
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
                $fetchType = ItemParent::TYPE_TUNING;
                break;
            case 'sport':
                $type = ItemParent::TYPE_SPORT;
                $fetchType = ItemParent::TYPE_SPORT;
                break;
            default:
                $type = ItemParent::TYPE_DEFAULT;
                $fetchType = [ItemParent::TYPE_DEFAULT, ItemParent::TYPE_DESIGN];
                break;
        }

        $listCars = [];

        $paginator = $this->itemModel->getPaginator([
            'parent' => [
                'id'        => $currentCarId,
                'link_type' => $fetchType
            ],
            'order' => $this->catalogue()->itemOrdering()
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
            $pPaginator = $this->picture->getPaginator([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => $currentCarId,
                'order'  => 'resolution_desc'
            ]);

            $pPaginator->setItemCountPerPage(4);

            $imageStorage = $this->imageStorage();
            $language = $this->language();

            $currentPictures = [];

            foreach ($pPaginator->getCurrentItems() as $pictureRow) {
                $imageInfo = $imageStorage->getFormatedImage(
                    $pictureRow['image_id'],
                    'picture-thumb-medium'
                );

                $currentPictures[] = [
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
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

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $canAcceptPicture = $this->user()->isAllowed('picture', 'accept');

        $inboxCount = 0;
        if ($canAcceptPicture) {
            $inboxCount = $this->getCarInboxCount($currentCarId);
        }

        $requireAttention = 0;
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $isModerator = $this->user()->inheritsRole('moder');
        if ($isModerator) {
            $requireAttention = $this->getItemModerAttentionCount($currentCarId);
        }

        $ids = [];
        foreach ($listCars as $car) {
            $ids[] = $car['id'];
        }

        $hasChildSpecs = $this->specsService->hasChildSpecs($ids);

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

        $picturesCount = $this->picture->getCount([
            'status' => Picture::STATUS_ACCEPTED,
            'item'   => [
                'ancestor_or_self' => $currentCarId
            ]
        ]);

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
            'picturesCount' => $picturesCount,
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
                'pictureFetcher' => new Item\PerspectivePictureFetcher([
                    'pictureModel'         => $this->picture,
                    'itemModel'            => $this->itemModel,
                    'perspective'          => $this->perspective,
                    'type'                 => $type == ItemParent::TYPE_DEFAULT ? $type : null,
                    'onlyExactlyPictures'  => false,
                    'dateSort'             => false,
                    'disableLargePictures' => false,
                    'perspectivePageId'    => null,
                    'onlyChilds'           => []
                ]),
                'listBuilder' => new Item\ListBuilder\CatalogueGroupItem([
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
                    'hasChildSpecs'    => $hasChildSpecs,
                    'type'             => $type
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
            'callback'  => function (Sql\Select $select) use ($carId) {
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
        return $this->picture->getCount([
            'status' => Picture::STATUS_INBOX,
            'item'   => [
                'ancestor_or_self' => $carId
            ]
        ]);
    }

    public function brandItemPicturesAction()
    {
        return $this->doBrandItemAction(function ($currentCar, $breadcrumbs, $brand, $brandItemCatname, $path) {

            $exact = (bool)$this->params('exact');

            $filter = [
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [],
                'order'  => 'perspectives'
            ];

            if ($exact) {
                $filter['item']['id'] = $currentCar['id'];
            } else {
                $filter['item']['ancestor_or_self'] = $currentCar['id'];
            }

            $modification = null;
            $modId = (int)$this->params('mod');
            if ($modId) {
                $modification = $this->modificationTable->select(['id' => (int)$modId])->current();
                if (! $modification) {
                    return $this->notFoundAction();
                }

                $filter['modification'] = $modId;
            }

            $paginator = $this->picture->getPaginator($filter);
            $paginator
                ->setItemCountPerPage($this->catalogue()->getPicturesPerPage())
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $picturesData = $this->pic()->listData($paginator->getCurrentItems(), [
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

    private function pictureAction(array $filter, callable $callback)
    {
        $pictureFilter = $filter;
        $pictureFilter['identity'] = (string)$this->params('picture_id');
        $picture = $this->picture->getRow($pictureFilter);

        if (! $picture) {
            return $this->notFoundAction();
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $isModer = $this->user()->inheritsRole('moder');

        if ($picture['status'] == Picture::STATUS_REMOVING) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $user = $this->user()->get();
            if (! $user) {
                return $this->notFoundAction();
            }

            if ($isModer || ($user['id'] == $picture['owner_id'])) {
                //$this->getResponse()->setStatusCode(404);
            } else {
                return $this->notFoundAction();
            }

            $filter['status'] = Picture::STATUS_REMOVING;
        } elseif ($picture['status'] == Picture::STATUS_INBOX) {
            $filter['status'] = Picture::STATUS_INBOX;
        } else {
            $filter['status'] = Picture::STATUS_ACCEPTED;
        }

        return $callback($filter, $picture);
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

            $filter = [
                'order' => 'perspectives',
                'item'  => []
            ];

            if ($this->params('exact')) {
                $filter['item']['id'] = $currentCar['id'];
            } else {
                $filter['item']['ancestor_or_self'] = $currentCar['id'];
            }


            return $this->pictureAction($filter, function ($filter, $picture) use ($breadcrumbs) {
                return [
                    'breadcrumbs' => $breadcrumbs,
                    'picture'     => array_replace(
                        /* @phan-suppress-next-line PhanUndeclaredMethod */
                        $this->pic()->picPageData($picture, $filter),
                        [
                            'galleryUrl' => $this->url()->fromRoute('catalogue', [
                                'action'  => 'brand-item-gallery',
                                'gallery' => $this->galleryType($picture)
                            ], [], true)
                        ]
                    ),
                    'allPicturesUrl' => $this->url()->fromRoute('catalogue', [
                        'action' => 'brand-item-pictures'
                    ], [], true)
                ];
            });
        });
    }

    public function brandItemGalleryAction()
    {
        return $this->doBrandItemAction(function ($currentCar) {

            $filter = [
                'item'  => [],
                'order' => 'perspectives'
            ];

            if ($this->params('exact')) {
                $filter['item']['id'] = $currentCar['id'];
            } else {
                $filter['item']['ancestor_or_self'] = $currentCar['id'];
            }


            switch ($this->params('gallery')) {
                case 'inbox':
                    $filter['status'] = Picture::STATUS_INBOX;
                    break;
                case 'removing':
                    $filter['status'] = Picture::STATUS_REMOVING;
                    break;
                default:
                    $filter['status'] = Picture::STATUS_ACCEPTED;
                    break;
            }

            /* @phan-suppress-next-line PhanUndeclaredMethod */
            return new JsonModel($this->pic()->gallery2($filter, [
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
                'order'  => $this->catalogue()->itemOrdering(),
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
                    'order'  => $this->catalogue()->itemOrdering(),
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

            $contributors = $this->userModel->getRows(['id' => array_keys($contribPairs)]);

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
                        $formatRequests[$idx++] = $picture['image_id'];
                        $allPictures[] = $picture;
                    }
                }
            }

            $imageStorage = $this->imageStorage();
            $imagesInfo = $imageStorage->getFormatedImages($formatRequests, 'picture-thumb');

            $names = $this->picture->getNameData($allPictures, [
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
                'order'        => $this->catalogue()->itemOrdering()
            ]);

            $paginator
                ->setItemCountPerPage($this->catalogue()->getCarsPerPage())
                ->setCurrentPageNumber($this->params('page'));

            if ($paginator->getTotalItemCount() <= 0) {
                return $this->notFoundAction();
            }

            return [
                'paginator' => $paginator,
                'listData'  => $this->car()->listData($paginator->getCurrentItems(), [
                    'pictureFetcher' => new Item\PerspectivePictureFetcher([
                        'pictureModel'         => $this->picture,
                        'itemModel'            => $this->itemModel,
                        'perspective'          => $this->perspective,
                        'type'                 => null,
                        'onlyExactlyPictures'  => false,
                        'dateSort'             => false,
                        'disableLargePictures' => false,
                        'perspectivePageId'    => null,
                        'onlyChilds'           => []
                    ]),
                    'listBuilder' => new Item\ListBuilder\Catalogue([
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
