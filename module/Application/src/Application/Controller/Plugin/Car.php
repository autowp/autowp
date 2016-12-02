<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Model\DbTable;
use Application\Model\Twins;
use Application\Service\SpecificationsService;
use Application\VehicleNameFormatter;

use Autowp\TextStorage\Service as TextStorage;

use Spec;

use Zend_Db_Expr;

class Car extends AbstractPlugin
{
    private $perspectiveCache = [];

    /**
     * @var DbTable\Vehicle\Language
     */
    private $carLangTable;

    /**
     * @var Twins
     */
    private $twins;

    /**
     * @var Spec
     */
    private $specTable;

    /**
     * @var TextStorage
     */
    private $textStorage;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var VehicleNameFormatter
     */
    private $vehicleNameFormatter;

    public function __construct(
        TextStorage $textStorage,
        SpecificationsService $specsService,
        VehicleNameFormatter $vehicleNameFormatter
    ) {

        $this->textStorage = $textStorage;
        $this->specsService = $specsService;
        $this->vehicleNameFormatter = $vehicleNameFormatter;
    }

    /**
     * @return Spec
     */
    private function getSpecTable()
    {
        return $this->specTable
            ? $this->specTable
            : $this->specTable = new Spec();
    }

    private function getCarLanguageTable()
    {
        return $this->carLangTable
            ? $this->carLangTable
            : $this->carLangTable = new DbTable\Vehicle\Language();
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

    /**
     * @return Car
     */
    public function __invoke()
    {
        return $this;
    }

    private function carsTotalPictures(array $carIds, $onlyExactly)
    {
        $result = [];
        foreach ($carIds as $carId) {
            $result[$carId] = null;
        }
        if (count($carIds)) {
            $pictureTable = $this->getPictureTable();
            $pictureTableAdapter = $pictureTable->getAdapter();

            $select = $pictureTableAdapter->select()
                ->where('pictures.status IN (?)', [
                    DbTable\Picture::STATUS_NEW,
                    DbTable\Picture::STATUS_ACCEPTED
                ]);

            if ($onlyExactly) {
                $select
                    ->from($pictureTable->info('name'), ['picture_item.item_id', new Zend_Db_Expr('COUNT(1)')])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->where('picture_item.item_id IN (?)', $carIds)
                    ->group('picture_item.item_id');
            } else {
                $select
                    ->from($pictureTable->info('name'), ['item_parent_cache.parent_id', new Zend_Db_Expr('COUNT(1)')])
                    ->join('picture_item', 'pictures.id = picture_item.picture_id', null)
                    ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                    ->where('item_parent_cache.parent_id IN (?)', $carIds)
                    ->group('item_parent_cache.parent_id');
            }

            $result = array_replace($result, $pictureTableAdapter->fetchPairs($select));
        }
        return $result;
    }

    public function listData($cars, array $options = [])
    {
        $type                 = isset($options['type']) ? $options['type'] : null;
        $disableTitle         = isset($options['disableTitle']) && $options['disableTitle'];
        $disableDescription   = isset($options['disableDescription']) && $options['disableDescription'];
        $disableDetailsLink   = isset($options['disableDetailsLink']) && $options['disableDetailsLink'];
        $detailsUrl           = isset($options['detailsUrl']) ? $options['detailsUrl'] : null;
        $allPicturesUrl       = isset($options['allPicturesUrl']) && $options['allPicturesUrl']
            ? $options['allPicturesUrl']
            : null;
        $typeUrl              = isset($options['typeUrl']) && $options['typeUrl'] ? $options['typeUrl'] : null;
        $specificationsUrl    = isset($options['specificationsUrl']) && $options['specificationsUrl']
            ? $options['specificationsUrl']
            : null;
        $onlyExactlyPictures  = isset($options['onlyExactlyPictures']) ? $options['onlyExactlyPictures'] : null;
        $hideEmpty            = isset($options['hideEmpty']) && $options['hideEmpty'];
        $disableTwins         = isset($options['disableTwins']) && $options['disableTwins'];
        $disableLargePictures = isset($options['disableLargePictures']) && $options['disableLargePictures'];
        $disableSpecs         = isset($options['disableSpecs']) && $options['disableSpecs'];
        $disableCategories    = isset($options['disableCategories']) && $options['disableCategories'];
        $picturesDateSort     = isset($options['picturesDateSort']) && $options['picturesDateSort'];
        $perspectiveGroup     = isset($options['perspectiveGroup']) ? (int)$options['perspectiveGroup'] : null;
        $callback             = isset($options['callback']) && $options['callback'] ? $options['callback'] : null;
        $allowUpPictures      = isset($options['allowUpPictures']) && $options['allowUpPictures'];
        $onlyChilds           = isset($options['onlyChilds']) && is_array($options['onlyChilds'])
            ? $options['onlyChilds']
            : [];
        $pictureUrlCallback   = isset($options['pictureUrl']) ? $options['pictureUrl'] : false;

        $controller = $this->getController();
        $pluginManager = $controller->getPluginManager();
        $picHelper = $pluginManager->get('pic');
        $userHelper = $controller->user();
        $imageStorage = $controller->imageStorage();

        $user = $userHelper->get();
        $specEditor = $userHelper->isAllowed('specifications', 'edit');
        $isCarModer = $userHelper->inheritsRole('cars-moder');
        $language = $controller->language();
        $catalogue = $controller->catalogue();

        $pictureTable = $this->getPictureTable();
        $carParentTable = new DbTable\Vehicle\ParentTable();
        $carParentAdapter = $carParentTable->getAdapter();
        $brandTable = new DbTable\Brand();
        $categoryTable = new DbTable\Category();

        $carIds = [];
        foreach ($cars as $car) {
            $carIds[] = (int)$car->id;
        }

        $hasSpecs = [];
        if (! $disableSpecs && ! $specificationsUrl) {
            $hasSpecs = $this->specsService->hasSpecs(1, $carIds);
        }

        if ($carIds) {
            $childsCounts = $carParentAdapter->fetchPairs(
                $carParentAdapter->select()
                    ->from($carParentTable->info('name'), ['parent_id', new Zend_Db_Expr('count(1)')])
                    ->where('parent_id IN (?)', $carIds)
                    ->group('parent_id')
            );
        } else {
            $childsCounts = [];
        }

        // categories
        $carsCategories = [];
        if ($carIds && ! $disableCategories) {
            $db = $categoryTable->getAdapter();
            $langExpr = $db->quoteInto(
                'category.id = category_language.category_id and category_language.language = ?',
                $language
            );
            $categoryRows = $db->fetchAll(
                $db->select()
                    ->from($categoryTable->info('name'), ['name', 'catname'])
                    ->join('category_car', 'category.id = category_car.category_id', null)
                    ->join('item_parent_cache', 'category_car.car_id = item_parent_cache.parent_id', 'item_id')
                    ->joinLeft('category_language', $langExpr, ['lang_name' => 'name'])
                    ->where('item_parent_cache.item_id IN (?)', $carIds)
                    ->group(['item_parent_cache.item_id', 'category.id'])
            );

            foreach ($categoryRows as $category) {
                $carId = (int)$category['item_id'];
                if (! isset($carsCategories[$carId])) {
                    $carsCategories[$carId] = [];
                }
                $carsCategories[$carId][] = [
                    'name' => $category['lang_name'] ? $category['lang_name'] : $category['name'],
                    'url'  => $controller->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $category['catname'],
                    ]),
                ];
            }
        }

        // twins
        $carsTwinsGroups = [];
        if ($carIds && ! $disableTwins) {
            $carsTwinsGroups = [];

            foreach ($this->getTwins()->getCarsGroups($carIds) as $carId => $twinsGroups) {
                $carsTwinsGroups[$carId] = [];
                foreach ($twinsGroups as $twinsGroup) {
                    $carsTwinsGroups[$carId][] = [
                        'url'  => $controller->url()->fromRoute('twins/group', [
                            'id' => $twinsGroup['id']
                        ]),
                    ];
                }
            }
        }

        // typecount
        $carsTypeCounts = [];
        if ($carIds && $typeUrl) {
            $rows = $carParentAdapter->fetchAll(
                $carParentAdapter->select()
                    ->from($carParentTable->info('name'), ['parent_id', 'type', 'count' => 'count(1)'])
                    ->where('parent_id IN (?)', $carIds)
                    ->where('type IN (?)', [
                        DbTable\Vehicle\ParentTable::TYPE_TUNING,
                        DbTable\Vehicle\ParentTable::TYPE_SPORT
                    ])
                    ->group(['parent_id', 'type'])
            );

            foreach ($rows as $row) {
                $carId = (int)$row['parent_id'];
                $typeId = (int)$row['type'];
                if (! isset($carsTypeCounts[$carId])) {
                    $carsTypeCounts[$carId] = [];
                }
                $carsTypeCounts[$carId][$typeId] = (int)$row['count'];
            }
        }

        // lang names
        $carsLangName = [];
        if ($carIds) {
            $carLangRows = $this->getCarLanguageTable()->fetchAll([
                'car_id IN (?)' => $carIds,
                'language = ?'  => $language,
                'length(name) > 0'
            ]);
            foreach ($carLangRows as $carLangRow) {
                $carsLangName[$carLangRow->car_id] = $carLangRow->name;
            }
        }

        // design projects
        $carsDesignProject = [];
        $designCarsRows = $carParentAdapter->fetchAll(
            $carParentAdapter->select()
                ->from('brands', [
                    'brand_name'    => 'name',
                    'brand_catname' => 'folder'
                ])
                ->join('brand_item', 'brands.id = brand_item.brand_id', [
                    'brand_item_catname' => 'catname'
                ])
                ->where('brand_item.type = ?', DbTable\BrandItem::TYPE_DESIGN)
                ->join('item_parent_cache', 'brand_item.car_id = item_parent_cache.parent_id', 'item_id')
                ->where('item_parent_cache.item_id IN (?)', $carIds ? $carIds : 0)
                ->group('item_parent_cache.item_id')
        );
        foreach ($designCarsRows as $designCarsRow) {
            $carsDesignProject[$designCarsRow['item_id']] = [
                'brandName' => $designCarsRow['brand_name'],
                'url'       => $controller->url()->fromRoute('catalogue', [
                    'action'        => 'brand-item',
                    'brand_catname' => $designCarsRow['brand_catname'],
                    'car_catname'   => $designCarsRow['brand_item_catname']
                ])
            ];
        }

        // total pictures
        $carsTotalPictures = $this->carsTotalPictures($carIds, $onlyExactlyPictures);
        $items = [];
        foreach ($cars as $car) {
            $totalPictures = isset($carsTotalPictures[$car->id]) ? $carsTotalPictures[$car->id] : null;

            $designProjectData = false;
            if (isset($carsDesignProject[$car->id])) {
                $designProjectData = $carsDesignProject[$car->id];
            }

            $categories = [];
            if (! $disableCategories) {
                $categories = isset($carsCategories[$car->id]) ? $carsCategories[$car->id] : [];
            }

            $pGroupId = null;
            $useLargeFormat = false;
            if ($perspectiveGroup) {
                $pGroupId = $perspectiveGroup;
            } else {
                $useLargeFormat = $totalPictures > 30 && ! $disableLargePictures;
                $pGroupId = $useLargeFormat ? 5 : 4;
            }

            $g = $this->getPerspectiveGroupIds($pGroupId);

            $carOnlyChilds = isset($onlyChilds[$car->id]) ? $onlyChilds[$car->id] : null;

            $pictures = $this->getOrientedPictureList(
                $car,
                $g,
                $onlyExactlyPictures,
                $type,
                $picturesDateSort,
                $allowUpPictures,
                $language,
                $picHelper,
                $catalogue,
                $carOnlyChilds,
                $useLargeFormat,
                $pictureUrlCallback
            );

            if ($hideEmpty) {
                $hasPictures = false;
                foreach ($pictures as $picture) {
                    if ($picture) {
                        $hasPictures = true;
                        break;
                    }
                }

                if (! $hasPictures) {
                    continue;
                }
            }

            $hasHtml = (bool)$car->full_text_id;

            $specsLinks = [];
            if (! $disableSpecs) {
                if ($specificationsUrl) {
                    $url = $specificationsUrl($car);
                    if ($url) {
                        $specsLinks[] = [
                            'name' => null,
                            'url'  => $url
                        ];
                    }
                } else {
                    if ($hasSpecs[$car->id]) {
                        foreach ($catalogue->cataloguePaths($car) as $path) {
                            $specsLinks[] = [
                                'name' => null,
                                'url'  => $controller->url()->fromRoute('catalogue', [
                                    'action'        => 'brand-item-specifications',
                                    'brand_catname' => $path['brand_catname'],
                                    'car_catname'   => $path['car_catname'],
                                    'path'          => $path['path']
                                ])
                            ];
                            break;
                        }
                    }
                }
            }

            $childsCount = isset($childsCounts[$car->id]) ? $childsCounts[$car->id] : 0;

            /*$spec = null;
            if ($car->spec_id) {
                $specRow = $this->getSpecTable()->find($car->spec_id)->current();
                if ($specRow) {
                    $spec = $specRow->short_name;
                }
            }*/

            $item = [
                'id'               => $car->id,
                'row'              => $car,
                'name'             => $car->name,
                'langName'         => isset($carsLangName[$car->id]) ? $carsLangName[$car->id] : null,
                'produced'         => $car->produced,
                'produced_exactly' => $car->produced_exactly,
                'designProject'    => $designProjectData,
                'totalPictures'    => $totalPictures,
                'categories'       => $categories,
                'pictures'         => $pictures,
                'hasHtml'          => $hasHtml,
                'hasChilds'        => $childsCount > 0,
                'childsCount'      => $childsCount,
                'specsLinks'       => $specsLinks,
                'largeFormat'      => $useLargeFormat,
            ];

            if (! $disableTwins) {
                $item['twinsGroups'] = isset($carsTwinsGroups[$car->id]) ? $carsTwinsGroups[$car->id] : [];
            }

            if (count($item['pictures']) < $item['totalPictures']) {
                if ($allPicturesUrl) {
                    $item['allPicturesUrl'] = $allPicturesUrl($car);
                }
            }

            if (! $disableDetailsLink && ($hasHtml || $childsCount > 0)) {
                $url = null;

                if (is_callable($detailsUrl)) {
                    $url = $detailsUrl($car);
                } else {
                    if ($detailsUrl !== false) {
                        $cataloguePaths = $catalogue->cataloguePaths($car);

                        $url = null;
                        foreach ($cataloguePaths as $cPath) {
                            $url = $controller->url()->fromRoute('catalogue', [
                                'action'        => 'brand-item',
                                'brand_catname' => $cPath['brand_catname'],
                                'car_catname'   => $cPath['car_catname'],
                                'path'          => $cPath['path']
                            ]);
                            break;
                        }
                    }
                }

                if ($url) {
                    $item['details'] = [
                        'url' => $url
                    ];
                }
            }

            if (! $disableDescription) {
                $description = null;
                if ($car->text_id) {
                    $description = $this->textStorage->getText($car->text_id);
                }
                $item['description'] = $description;
            }

            if ($specEditor) {
                $item['specEditorUrl'] = $controller->url()->fromRoute('cars/params', [
                    'action' => 'car-specifications-editor',
                    'car_id' => $car->id
                ]);
            }

            if ($isCarModer) {
                $item['moderUrl'] = $controller->url()->fromRoute('moder/cars/params', [
                    'action' => 'car',
                    'car_id' => $car->id
                ]);
            }

            if ($typeUrl) {
                $tuningCount = isset($carsTypeCounts[$car->id][DbTable\Vehicle\ParentTable::TYPE_TUNING])
                    ? $carsTypeCounts[$car->id][DbTable\Vehicle\ParentTable::TYPE_TUNING]
                    : 0;
                if ($tuningCount) {
                    $item['tuning'] = [
                        'count' => $tuningCount,
                        'url'   => $typeUrl($car, DbTable\Vehicle\ParentTable::TYPE_TUNING)
                    ];
                }

                $sportCount = isset($carsTypeCounts[$car->id][DbTable\Vehicle\ParentTable::TYPE_SPORT])
                    ? $carsTypeCounts[$car->id][DbTable\Vehicle\ParentTable::TYPE_SPORT]
                    : 0;
                if ($sportCount) {
                    $item['sport'] = [
                        'count' => $sportCount,
                        'url'   => $typeUrl($car, DbTable\Vehicle\ParentTable::TYPE_SPORT)
                    ];
                }
            }

            if ($callback) {
                $callback($item);
            }

            $items[] = $item;
        }

        // collect all pictures
        $allPictures = [];
        $allFormatRequests = [];
        foreach ($items as $item) {
            foreach ($item['pictures'] as $picture) {
                if ($picture) {
                    $row = $picture['row'];
                    $allPictures[] = $row;
                    $allFormatRequests[$picture['format']][$row['id']] = $catalogue->getPictureFormatRequest($row);
                }
            }
        }


        // prefetch names
        $pictureNames = $pictureTable->getNameData($allPictures, [
            'language' => $language
        ]);

        // prefetch images
        $imagesInfo = [];
        foreach ($allFormatRequests as $format => $requests) {
            $imagesInfo[$format] = $imageStorage->getFormatedImages($requests, $format);
        }

        // populate prefetched
        foreach ($items as &$item) {
            foreach ($item['pictures'] as &$picture) {
                if ($picture) {
                    $id = $picture['row']['id'];
                    $format = $picture['format'];

                    $picture['name'] = isset($pictureNames[$id]) ? $pictureNames[$id] : null;
                    $picture['src'] = isset($imagesInfo[$format][$id]) ? $imagesInfo[$format][$id]->getSrc() : null;
                    unset($picture['row'], $picture['format']);
                }
            }
        }
        unset($item, $picture);

        return [
            'specEditor'         => $specEditor,
            'isCarModer'         => $isCarModer,
            'disableDescription' => $disableDescription,
            'disableDetailsLink' => $disableDetailsLink,
            'disableTitle'       => $disableTitle,
            'items'              => $items,
        ];
    }

    private function getPictureTable()
    {
        return $this->getController()->catalogue()->getPictureTable();
    }

    private function getPerspectiveGroupIds($pageId)
    {
        if (! isset($this->perspectiveCache[$pageId])) {
            $perspectivesGroups = new DbTable\Perspective\Group();
            $db = $perspectivesGroups->getAdapter();
            $this->perspectiveCache[$pageId] = $db->fetchCol(
                $db->select()
                    ->from($perspectivesGroups->info('name'), 'id')
                    ->where('page_id = ?', $pageId)
                    ->order('position')
            );
        }

        return $this->perspectiveCache[$pageId];
    }

    private function getPictureSelect($car, array $options)
    {
        $defaults = [
            'onlyExactlyPictures' => false,
            'perspectiveGroup'    => false,
            'type'                => null,
            'exclude'             => [],
            'dateSort'            => false,
            'onlyChilds'          => null
        ];
        $options = array_merge($defaults, $options);

        $pictureTable = $this->getPictureTable();
        $db = $pictureTable->getAdapter();
        $select = $db->select()
            ->from(
                $pictureTable->info('name'),
                [
                    'id', 'name', 'type', 'brand_id', 'engine_id', 'factory_id',
                    'image_id', 'crop_left', 'crop_top',
                    'crop_width', 'crop_height', 'width', 'height', 'identity'
                ]
            )
            ->join('picture_item', 'pictures.id = picture_item.picture_id', 'perspective_id')
            ->where('pictures.status IN (?)', [
                DbTable\Picture::STATUS_ACCEPTED,
                DbTable\Picture::STATUS_NEW
            ])
            ->limit(1);

        $order = [];

        if ($options['onlyExactlyPictures']) {
            $select->where('picture_item.item_id = ?', $car->id);
        } else {
            $select
                ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', null)
                ->join('cars', 'picture_item.item_id = cars.id', null)
                ->where('item_parent_cache.parent_id = ?', $car->id);

            $order[] = 'cars.is_concept asc';
            $order[] = 'item_parent_cache.sport asc';
            $order[] = 'item_parent_cache.tuning asc';

            if (isset($options['type'])) {
                switch ($options['type']) {
                    case DbTable\Vehicle\ParentTable::TYPE_DEFAULT:
                        break;
                    case DbTable\Vehicle\ParentTable::TYPE_TUNING:
                        $select->where('item_parent_cache.tuning');
                        break;
                    case DbTable\Vehicle\ParentTable::TYPE_SPORT:
                        $select->where('item_parent_cache.sport');
                        break;
                }
            }
        }

        if ($options['perspectiveGroup']) {
            $select
                ->join(
                    ['mp' => 'perspectives_groups_perspectives'],
                    'picture_item.perspective_id = mp.perspective_id',
                    null
                )
                ->where('mp.group_id = ?', $options['perspectiveGroup']);

            $order[] = 'mp.position';
        }

        if ($options['exclude']) {
            $select->where('pictures.id not in (?)', $options['exclude']);
        }

        if ($options['dateSort']) {
            $select->join(['picture_car' => 'cars'], 'cars.id = picture_car.id', null);
            $order = array_merge($order, ['picture_car.begin_order_cache', 'picture_car.end_order_cache']);
        }
        $order = array_merge($order, ['pictures.width DESC', 'pictures.height DESC']);

        $select->order($order);

        if ($options['onlyChilds']) {
            $select
                ->join(
                    ['pi_oc' => 'picture_item'],
                    'pi_oc.picture_id = pictures.id'
                )
                ->join(
                    ['cpc_oc' => 'item_parent_cache'],
                    'cpc_oc.item_id = pi_oc.item_id',
                    null
                )
                ->where('cpc_oc.parent_id IN (?)', $options['onlyChilds']);
        }

        return $select;
    }

    private function getOrientedPictureList(
        $car,
        array $perspectiveGroupIds,
        $onlyExactlyPictures,
        $type,
        $dateSort,
        $allowUpPictures,
        $language,
        $picHelper,
        $catalogue,
        $onlyChilds,
        $useLargeFormat,
        $urlCallback
    ) {

        $pictures = [];
        $usedIds = [];

        $pictureTable = $this->getPictureTable();
        $db = $pictureTable->getAdapter();

        foreach ($perspectiveGroupIds as $groupId) {
            $select = $this->getPictureSelect($car, [
                'onlyExactlyPictures' => $onlyExactlyPictures,
                'perspectiveGroup'    => $groupId,
                'type'                => $type,
                'exclude'             => $usedIds,
                'dateSort'            => $dateSort,
                'onlyChilds'          => $onlyChilds
            ]);

            $picture = $db->fetchRow($select);

            if ($picture) {
                $pictures[] = $picture;
                $usedIds[] = (int)$picture['id'];
            } else {
                $pictures[] = null;
            }
        }

        $needMore = count($perspectiveGroupIds) - count($usedIds);

        if ($needMore > 0) {
            $select = $this->getPictureSelect($car, [
                'onlyExactlyPictures' => $onlyExactlyPictures,
                'type'                => $type,
                'exclude'             => $usedIds,
                'dateSort'            => $dateSort,
                'onlyChilds'          => $onlyChilds
            ]);

            $rows = $db->fetchAll(
                $select->limit($needMore)
            );
            $morePictures = [];
            foreach ($rows as $row) {
                $morePictures[] = $row;
            }

            foreach ($pictures as $key => $picture) {
                if (count($morePictures) <= 0) {
                    break;
                }
                if (! $picture) {
                    $pictures[$key] = array_shift($morePictures);
                }
            }
        }

        /*$nothingFound = true;
        foreach ($pictures as $picture) {
            if ($picture) {
                $nothingFound = false;
                break;
            }
        }*/

        $notEmptyPics = [];
        foreach ($pictures as $picture) {
            if ($picture) {
                $notEmptyPics[] = $picture;
            }
        }

        $result = [];
        foreach ($pictures as $idx => $picture) {
            if ($picture) {
                $pictureId = $picture['id'];

                $format = $useLargeFormat && $idx == 0 ? 'picture-thumb-medium' : 'picture-thumb';

                if ($urlCallback) {
                    $url = $urlCallback($car, $picture);
                } else {
                    $url = $picHelper->href($picture);
                }

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

    public function formatName(DbTable\Vehicle\Row $vehicle, $language)
    {
        return $this->vehicleNameFormatter->format(
            $vehicle->getNameData($language),
            $language
        );
    }
}
