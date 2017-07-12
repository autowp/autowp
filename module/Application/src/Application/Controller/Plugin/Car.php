<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Application\Model\DbTable;
use Application\Model\Item\PictureFetcher;
use Application\Model\Twins;
use Application\Service\SpecificationsService;
use Application\ItemNameFormatter;

use Autowp\TextStorage\Service as TextStorage;

use Zend_Db_Expr;

class Car extends AbstractPlugin
{
    /**
     * @var DbTable\Item\Language
     */
    private $carLangTable;

    /**
     * @var Twins
     */
    private $twins;

    /**
     * @var TextStorage
     */
    private $textStorage;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    private $categoryPictureFetcher;

    public function __construct(
        TextStorage $textStorage,
        SpecificationsService $specsService,
        ItemNameFormatter $itemNameFormatter
    ) {

        $this->textStorage = $textStorage;
        $this->specsService = $specsService;
        $this->itemNameFormatter = $itemNameFormatter;
    }

    private function getCarLanguageTable()
    {
        return $this->carLangTable
            ? $this->carLangTable
            : $this->carLangTable = new DbTable\Item\Language();
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

    private function getCategoryPictureFetcher()
    {
        return $this->categoryPictureFetcher
            ? $this->categoryPictureFetcher
            : $this->categoryPictureFetcher = new \Application\Model\Item\DistinctItemPictureFetcher([
                'dateSort' => false
            ]);
    }

    public function listData($cars, array $options = [])
    {
        $listBuilder          = $options['listBuilder'];
        $pictureFetcher       = $options['pictureFetcher'];
        if (! $pictureFetcher instanceof PictureFetcher) {
            throw new \Exception("Invalid picture fetcher provided");
        }
        $disableTitle         = isset($options['disableTitle']) && $options['disableTitle'];
        $disableDescription   = isset($options['disableDescription']) && $options['disableDescription'];
        $disableDetailsLink   = isset($options['disableDetailsLink']) && $options['disableDetailsLink'];
        $onlyExactlyPictures  = isset($options['onlyExactlyPictures']) ? $options['onlyExactlyPictures'] : null;
        $hideEmpty            = isset($options['hideEmpty']) && $options['hideEmpty'];
        $disableTwins         = isset($options['disableTwins']) && $options['disableTwins'];
        $disableSpecs         = isset($options['disableSpecs']) && $options['disableSpecs'];
        $disableCategories    = isset($options['disableCategories']) && $options['disableCategories'];
        $callback             = isset($options['callback']) && $options['callback'] ? $options['callback'] : null;
        $thumbColumns         = isset($options['thumbColumns']) && $options['thumbColumns']
                                    ? $options['thumbColumns'] : 4;

        $controller = $this->getController();
        $pluginManager = $controller->getPluginManager();
        $picHelper = $pluginManager->get('pic');
        $userHelper = $controller->user();
        $imageStorage = $controller->imageStorage();

        $specEditor = $userHelper->isAllowed('specifications', 'edit');
        $isCarModer = $userHelper->inheritsRole('cars-moder');
        $language = $controller->language();
        $catalogue = $controller->catalogue();

        $pictureTable = $this->getPictureTable();
        $itemParentTable = new DbTable\Item\ParentTable();
        $itemParentAdapter = $itemParentTable->getAdapter();
        $itemTable = new DbTable\Item();
        $itemLanguageTable = new DbTable\Item\Language();

        $carIds = [];
        foreach ($cars as $car) {
            $carIds[] = (int)$car->id;
        }

        if ($carIds) {
            $childsCounts = $itemParentAdapter->fetchPairs(
                $itemParentAdapter->select()
                    ->from($itemParentTable->info('name'), ['parent_id', new Zend_Db_Expr('count(1)')])
                    ->where('parent_id IN (?)', $carIds)
                    ->group('parent_id')
            );
        } else {
            $childsCounts = [];
        }

        // categories
        $carsCategories = [];
        if ($carIds && ! $disableCategories) {
            $db = $itemTable->getAdapter();
            $langExpr = $db->quoteInto(
                'item.id = item_language.item_id and item_language.language = ?',
                $language
            );
            $categoryRows = $db->fetchAll(
                $db->select()
                    ->from($itemTable->info('name'), [
                        'catname', 'begin_year', 'end_year',
                        'name' => new Zend_Db_Expr('IF(LENGTH(item_language.name)>0,item_language.name,item.name)')
                    ])
                    ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft('item_language', $langExpr, ['lang_name' => 'name'])
                    ->join('item_parent', 'item.id = item_parent.parent_id', null)
                    ->join(['top_item' => 'item'], 'item_parent.item_id = top_item.id', null)
                    ->where('top_item.item_type_id IN (?)', [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])
                    ->join('item_parent_cache', 'top_item.id = item_parent_cache.parent_id', 'item_id')
                    ->where('item_parent_cache.item_id IN (?)', $carIds)
                    ->group(['item_parent_cache.item_id', 'item.id'])
            );

            foreach ($categoryRows as $category) {
                $carId = (int)$category['item_id'];
                if (! isset($carsCategories[$carId])) {
                    $carsCategories[$carId] = [];
                }
                $carsCategories[$carId][] = [
                    'name' => $this->itemNameFormatter->format(
                        $category,
                        $language
                    ),
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
        if ($carIds && $listBuilder->isTypeUrlEnabled()) {
            $rows = $itemParentAdapter->fetchAll(
                $itemParentAdapter->select()
                    ->from($itemParentTable->info('name'), ['parent_id', 'type', 'count' => 'count(1)'])
                    ->where('parent_id IN (?)', $carIds)
                    ->where('type IN (?)', [
                        DbTable\Item\ParentTable::TYPE_TUNING,
                        DbTable\Item\ParentTable::TYPE_SPORT
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
                'item_id IN (?)' => $carIds,
                'language = ?'  => $language,
                'length(name) > 0'
            ]);
            foreach ($carLangRows as $carLangRow) {
                $carsLangName[$carLangRow->item_id] = $carLangRow->name;
            }
        }

        // total pictures
        $carsTotalPictures = $pictureFetcher->getTotalPictures($carIds, $onlyExactlyPictures);
        $items = [];
        foreach ($cars as $car) {
            $totalPictures = isset($carsTotalPictures[$car->id]) ? $carsTotalPictures[$car->id] : null;

            // design projects
            $designCarsRow = $itemParentAdapter->fetchRow(
                $itemParentAdapter->select()
                    ->from('item', [
                        'brand_name'    => 'name',
                        'brand_catname' => 'catname'
                    ])
                    ->join('item_parent', 'item.id = item_parent.parent_id', [
                        'brand_item_catname' => 'catname'
                    ])
                    ->where('item_parent.type = ?', DbTable\Item\ParentTable::TYPE_DESIGN)
                    ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.parent_id', 'item_id')
                    ->where('item_parent_cache.item_id = ?', $car->id)
                    ->limit(1)
            );
            $designProjectData = false;
            if ($designCarsRow) {
                $designProjectData = [
                    'brandName' => $designCarsRow['brand_name'],
                    'url'       => $controller->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item',
                        'brand_catname' => $designCarsRow['brand_catname'] ? $designCarsRow['brand_catname'] : 'test',
                        'car_catname'   => $designCarsRow['brand_item_catname']
                    ])
                ];
            }

            $categories = [];
            if (! $disableCategories) {
                $categories = isset($carsCategories[$car->id]) ? $carsCategories[$car->id] : [];
            }

            $cFetcher = $pictureFetcher;
            if ($car['item_type_id'] == DbTable\Item\Type::CATEGORY) {
                $cFetcher = $this->getCategoryPictureFetcher();
            }

            $pictures = $cFetcher->fetch($car->toArray(), [
                'totalPictures' => $totalPictures
            ]);
            $largeFormat = false;
            foreach ($pictures as &$picture) {
                if ($picture) {
                    if (isset($picture['isVehicleHood']) && $picture['isVehicleHood']) {
                        $url = $picHelper->href($picture['row']);
                    } else {
                        $url = $listBuilder->getPictureUrl($car, $picture['row']);
                    }
                    $picture['url'] = $url;
                    if ($picture['format'] == 'picture-thumb-medium') {
                        $largeFormat = true;
                    }
                }
            }
            unset($picture);

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

            $db = $itemLanguageTable->getAdapter();
            $orderExpr = $db->quoteInto('language = ? desc', $language);
            $itemLanguageRows = $itemLanguageTable->fetchAll([
                'item_id = ?' => $car['id']
            ], new \Zend_Db_Expr($orderExpr));

            $textIds = [];
            $fullTextIds = [];
            foreach ($itemLanguageRows as $itemLanguageRow) {
                if ($itemLanguageRow->text_id) {
                    $textIds[] = $itemLanguageRow->text_id;
                }
                if ($itemLanguageRow->full_text_id) {
                    $fullTextIds[] = $itemLanguageRow->full_text_id;
                }
            }

            $description = null;
            if ($textIds) {
                $description = $this->textStorage->getFirstText($textIds);
            }

            $text = null;
            if ($fullTextIds) {
                $text = $this->textStorage->getFirstText($fullTextIds);
            }

            $hasHtml = (bool)$text;

            $specsLinks = [];
            if (! $disableSpecs) {
                $url = $listBuilder->getSpecificationsUrl($car);
                if ($url) {
                    $specsLinks[] = [
                        'name' => null,
                        'url'  => $url
                    ];
                }
            }

            $childsCount = isset($childsCounts[$car->id]) ? $childsCounts[$car->id] : 0;

            $vehiclesOnEngine = [];
            if ($car->item_type_id == DbTable\Item\Type::ENGINE) {
                $vehiclesOnEngine = $this->getVehiclesOnEngine($car);
            }

            $item = [
                'id'               => $car->id,
                'itemTypeId'       => $car['item_type_id'],
                'name'             => $car->name,
                'nameData'         => $car->getNameData($language),
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
                'largeFormat'      => $largeFormat,
                'vehiclesOnEngine' => $vehiclesOnEngine
            ];

            if (! $disableTwins) {
                $item['twinsGroups'] = isset($carsTwinsGroups[$car->id]) ? $carsTwinsGroups[$car->id] : [];
            }

            if (count($item['pictures']) < $item['totalPictures']) {
                $item['allPicturesUrl'] = $listBuilder->getPicturesUrl($car);
            }

            $item['uploadUrl'] = $controller->url()->fromRoute('upload/params', [
                'action'  => 'index',
                'item_id' => $car['id']
            ]);

            if (! $disableDetailsLink && ($hasHtml || $childsCount > 0)) {
                $url = $listBuilder->getDetailsUrl($car);

                if ($url) {
                    $item['details'] = [
                        'url' => $url
                    ];
                }
            }

            if (! $disableDescription) {
                $item['description'] = $description;
            }

            if ($specEditor) {
                $item['specEditorUrl'] = $controller->url()->fromRoute('cars/params', [
                    'action'  => 'car-specifications-editor',
                    'item_id' => $car->id
                ]);
            }

            if ($isCarModer) {
                $item['moderUrl'] = '/ng/moder/items/item/' . $car['id'];
            }

            if ($listBuilder->isTypeUrlEnabled()) {
                $tuningCount = isset($carsTypeCounts[$car->id][DbTable\Item\ParentTable::TYPE_TUNING])
                    ? $carsTypeCounts[$car->id][DbTable\Item\ParentTable::TYPE_TUNING]
                    : 0;
                if ($tuningCount) {
                    $url = $listBuilder->getTypeUrl($car, DbTable\Item\ParentTable::TYPE_TUNING);
                    $item['tuning'] = [
                        'count' => $tuningCount,
                        'url'   => $url
                    ];
                }

                $sportCount = isset($carsTypeCounts[$car->id][DbTable\Item\ParentTable::TYPE_SPORT])
                    ? $carsTypeCounts[$car->id][DbTable\Item\ParentTable::TYPE_SPORT]
                    : 0;
                if ($sportCount) {
                    $url = $listBuilder->getTypeUrl($car, DbTable\Item\ParentTable::TYPE_SPORT);
                    $item['sport'] = [
                        'count' => $sportCount,
                        'url'   => $url
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
            'thumbColumns'       => $thumbColumns
        ];
    }

    private function getVehiclesOnEngine($engine)
    {
        $result = [];

        $itemModel = new \Application\Model\Item();

        $ids = $itemModel->getEngineVehiclesGroups($engine->id, [
            'groupJoinLimit' => 3
        ]);

        if ($ids) {
            $controller = $this->getController();
            $language = $controller->language();
            $catalogue = $controller->catalogue();
            $itemTable = new DbTable\Item();

            $rows = $itemTable->fetchAll([
                'id in (?)' => $ids
            ], $catalogue->itemOrdering());
            foreach ($rows as $row) {
                $cataloguePaths = $catalogue->getCataloguePaths($row['id']);
                foreach ($cataloguePaths as $cPath) {
                    $result[] = [
                        'name' => $row->getNameData($language),
                        'url'  => $controller->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item',
                            'brand_catname' => $cPath['brand_catname'],
                            'car_catname'   => $cPath['car_catname'],
                            'path'          => $cPath['path']
                        ])
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    private function getPictureTable()
    {
        return $this->getController()->catalogue()->getPictureTable();
    }

    public function formatName(DbTable\Item\Row $vehicle, $language)
    {
        return $this->itemNameFormatter->format(
            $vehicle->getNameData($language),
            $language
        );
    }
}
