<?php

namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

use Autowp\User\Model\DbTable\User as UserTable;

use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\DbTable\Picture;
use Application\Model\PictureItem;
use Application\Paginator\Adapter\Zend1DbSelect;
use Application\Paginator\Adapter\Zend1DbTableSelect;
use Application\PictureNameFormatter;
use Application\Service\SpecificationsService;
use Application\ItemNameFormatter;

use Exception;

use Zend_Db_Expr;
use Zend_Db_Select;
use Zend_Db_Table_Select;

class Pic extends AbstractPlugin
{
    /**
     * @var DbTable\Picture\View
     */
    private $pictureViewTable = null;

    private $moderVoteTable = null;

    private $textStorage;

    private $translator;

    /**
     * @var PictureNameFormatter
     */
    private $pictureNameFormatter;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var PictureItem
     */
    private $pictureItem;

    /**
     * @var DbTable\Picture
     */
    private $pictureTable;

    public function __construct(
        $textStorage,
        $translator,
        PictureNameFormatter $pictureNameFormatter,
        ItemNameFormatter $itemNameFormatter,
        SpecificationsService $specsService,
        PictureItem $pictureItem,
        $httpRouter
    ) {

        $this->textStorage = $textStorage;
        $this->translator = $translator;
        $this->pictureNameFormatter = $pictureNameFormatter;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->specsService = $specsService;
        $this->pictureItem = $pictureItem;
        $this->httpRouter = $httpRouter;

        $this->pictureTable = new DbTable\Picture();
    }

    /**
     * @return DbTable\Picture\ModerVote
     */
    private function getModerVoteTable()
    {
        return $this->moderVoteTable
            ? $this->moderVoteTable
            : $this->moderVoteTable = new DbTable\Picture\ModerVote();
    }

    /**
     * @return DbTable\Picture\View
     */
    private function getPictureViewTable()
    {
        return $this->pictureViewTable
            ? $this->pictureViewTable
            : $this->pictureViewTable = new DbTable\Picture\View();
    }

    public function href($row, array $options = [])
    {
        $defaults = [
            'fallback'  => true,
            'canonical' => false,
            'uri'       => null
        ];
        $options = array_replace($defaults, $options);

        $controller = $this->getController();

        $url = null;

        $carIds = $this->pictureItem->getPictureItems($row['id']);
        if ($carIds) {
            $carId = $carIds[0];
            $itemParentTable = new DbTable\Item\ParentTable();
            $paths = $itemParentTable->getPaths($carId, [
                'breakOnFirst' => true
            ]);

            if (count($paths) > 0) {
                $path = $paths[0];

                if ($path['car_catname']) {
                    $url = $controller->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-picture',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path'],
                        'picture_id'    => $row['identity']
                    ], [
                        'force_canonical' => $options['canonical']
                    ]);
                } else {
                    $perspectiveId = $this->pictureItem->getPerspective($row['id'], $carId);

                    switch ($perspectiveId) {
                        case 22:
                            $action = 'logotypes-picture';
                            break;
                        case 25:
                            $action = 'mixed-picture';
                            break;
                        default:
                            $action = 'other-picture';
                            break;
                    }

                    $url = $controller->url()->fromRoute('catalogue', [
                        'action'        => $action,
                        'brand_catname' => $path['brand_catname'],
                        'picture_id'    => $row['identity']
                    ], [
                        'force_canonical' => $options['canonical']
                    ]);
                }
            }
        }

        if ($options['fallback'] && ! $url) {
            $url = $this->url($row['identity'], $options['canonical']);
        }

        return $url;
    }

    public function url($identity, $absolute = false, $uri = null)
    {
        return $this->httpRouter->assemble([
            'picture_id' => $identity
        ], [
            'name'            => 'picture/picture',
            'force_canonical' => $absolute,
            'uri'             => $uri
        ]);
    }

    public function listData($pictures, array $options = [])
    {
        $defaults = [
            'width'            => null,
            'disableBehaviour' => false,
            'url'              => null
        ];

        $options = array_replace($defaults, $options);

        $urlCallback = $options['url'];

        $colClass = '';
        $width = null;

        if ($options['width']) {
            $width = (int)$options['width'];
            if (! $colClass) {
                $colClass = 'col-lg-' . (12 / $width) . ' col-md-' . (12 / $width);
            }
        }

        $controller = $this->getController();
        $imageStorage = $controller->imageStorage();
        $isModer = $controller->user()->inheritsRole('pictures-moder');
        $userId = null;
        if ($controller->user()->logedIn()) {
            $user = $controller->user()->get();
            $userId = $user ? $user->id : null;
        }

        $language = $controller->language();

        $ids = [];

        if (is_array($pictures)) {
            $rows = [];
            foreach ($pictures as $picture) {
                $ids[] = $picture['id'];
                $rows[] = $picture->toArray();
            }

            // moder votes
            $moderVotes = [];
            if (count($ids)) {
                $moderVoteTable = $this->getModerVoteTable();
                $db = $moderVoteTable->getAdapter();

                $voteRows = $db->fetchAll(
                    $db->select()
                        ->from($moderVoteTable->info('name'), [
                            'picture_id',
                            'vote'  => new Zend_Db_Expr('sum(if(vote, 1, -1))'),
                            'count' => 'count(1)'
                        ])
                        ->where('picture_id in (?)', $ids)
                        ->group('picture_id')
                );

                foreach ($voteRows as $row) {
                    $moderVotes[$row['picture_id']] = [
                        'moder_votes'       => (int)$row['vote'],
                        'moder_votes_count' => (int)$row['count']
                    ];
                }
            }

            // views
            $views = [];
            if (! $options['disableBehaviour']) {
                $views = $this->getPictureViewTable()->getValues($ids);
            }

            // messages
            $messages = [];
            if (! $options['disableBehaviour'] && count($ids)) {
                $ctTable = new DbTable\Comment\Topic();
                $db = $ctTable->getAdapter();
                $messages = $db->fetchPairs(
                    $ctTable->select()
                        ->from($ctTable->info('name'), ['item_id', 'messages'])
                        ->where('item_id in (?)', $ids)
                        ->where('type_id = ?', DbTable\Comment\Message::PICTURES_TYPE_ID)
                );
            }

            foreach ($rows as &$row) {
                $id = $row['id'];
                if (isset($moderVotes[$id])) {
                    $vote = $moderVotes[$id];
                    $row['moder_votes'] = $vote['moder_votes'];
                    $row['moder_votes_count'] = $vote['moder_votes_count'];
                } else {
                    $row['moder_votes'] = null;
                    $row['moder_votes_count'] = 0;
                }
                if (! $options['disableBehaviour']) {
                    if (isset($views[$id])) {
                        $row['views'] = $views[$id];
                    } else {
                        $row['views'] = 0;
                    }
                    if (isset($messages[$id])) {
                        $row['messages'] = $messages[$id];
                    } else {
                        $row['messages'] = 0;
                    }
                }
            }
            unset($row);
        } elseif ($pictures instanceof Zend_Db_Table_Select) {
            $table = $pictures->getTable();
            $db = $table->getAdapter();

            $select = clone $pictures;
            $bind = [];

            $select
                ->reset(Zend_Db_Select::COLUMNS)
                ->setIntegrityCheck(false)
                ->columns([
                    'pictures.id', 'pictures.identity', 'pictures.name',
                    'pictures.width', 'pictures.height',
                    'pictures.crop_left', 'pictures.crop_top',
                    'pictures.crop_width', 'pictures.crop_height',
                    'pictures.status', 'pictures.image_id'
                ]);

            $select
                ->group('pictures.id')
                ->joinLeft('pictures_moder_votes', 'pictures.id = pictures_moder_votes.picture_id', [
                    'moder_votes'       => 'sum(if(pictures_moder_votes.vote, 1, -1))',
                    'moder_votes_count' => 'count(pictures_moder_votes.picture_id)'
                ]);



            if (! $options['disableBehaviour']) {
                $select
                    ->joinLeft(['pv' => 'picture_view'], 'pictures.id = pv.picture_id', 'views')
                    ->joinLeft(
                        ['ct' => 'comment_topic'],
                        'ct.type_id = :type_id and ct.item_id = pictures.id',
                        'messages'
                    );

                $bind['type_id'] = DbTable\Comment\Message::PICTURES_TYPE_ID;
            }

            $rows = $db->fetchAll($select, $bind);


            foreach ($rows as $idx => $picture) {
                $ids[] = (int)$picture['id'];
            }
        } else {
            throw new Exception("Unexpected type of pictures");
        }

        //print $select;

        // prefetch
        $requests = [];
        foreach ($rows as $idx => $picture) {
            $requests[$idx] = DbTable\Picture\Row::buildFormatRequest($picture);
        }

        $imagesInfo = $imageStorage->getFormatedImages($requests, 'picture-thumb');

        // names
        $names = $this->pictureTable->getNameData($rows, [
            'language' => $language
        ]);

        // comments
        if (! $options['disableBehaviour']) {
            if ($userId) {
                $ctTable = new DbTable\Comment\Topic();
                $newMessages = $ctTable->getNewMessages(
                    DbTable\Comment\Message::PICTURES_TYPE_ID,
                    $ids,
                    $userId
                );
            }
        }

        $itemParentTable = new DbTable\Item\ParentTable();

        $items = [];
        foreach ($rows as $idx => $row) {
            $id = (int)$row['id'];

            $name = isset($names[$id]) ? $names[$id] : null;

            if ($urlCallback) {
                $url = $urlCallback($row);
            } else {
                $url = $this->href($row);
            }

            $item = [
                'id'        => $id,
                'name'      => $name,
                'url'       => $url,
                'src'       => isset($imagesInfo[$idx]) ? $imagesInfo[$idx]->getSrc() : null,
                'moderVote' => $row['moder_votes_count'] > 0 ? $row['moder_votes'] : null,
                //'perspective_id' => $row['perspective_id']
            ];

            if (! $options['disableBehaviour']) {
                $msgCount = $row['messages'];
                $newMsgCount = 0;
                if ($userId) {
                    $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
                }

                $item = array_replace($item, [
                    'resolution'     => (int)$row['width'] . '×' . (int)$row['height'],
                    'cropped'        => DbTable\Picture\Row::checkCropParameters($row),
                    'cropResolution' => $row['crop_width'] . '×' . $row['crop_height'],
                    'status'         => $row['status'],
                    'views'          => (int)$row['views'],
                    'msgCount'       => $msgCount,
                    'newMsgCount'    => $newMsgCount,
                ]);
            }



            $items[] = $item;
        }

        return [
            'items'            => $items,
            'colClass'         => $colClass,
            'disableBehaviour' => $options['disableBehaviour'],
            'isModer'          => $isModer,
            'width'            => $width
        ];
    }

    private function picPageItemsData($picture, $carIds)
    {
        $controller = $this->getController();
        $catalogue = $controller->catalogue();

        $language = $controller->language();
        $isModer = $controller->user()->inheritsRole('moder');

        $itemTable = $catalogue->getItemTable();
        $itemLanguageTable = new DbTable\Item\Language();

        $db = $this->pictureTable->getAdapter();

        if ($isModer) {
            $perspectives = new DbTable\Perspective();

            $multioptions = $perspectives->getAdapter()->fetchPairs(
                $perspectives->getAdapter()->select()
                    ->from($perspectives->info('name'), ['id', 'name'])
                    ->order('position')
            );

            $multioptions = array_replace([
                '' => '--'
            ], $multioptions);
        }

        $itemRows = [];
        if ($carIds) {
            $itemRows = $itemTable->fetchAll([
                'id IN (?)'        => $carIds,
                'item_type_id = ?' => DbTable\Item\Type::VEHICLE
            ]);
        }
        $itemsCount = count($itemRows);

        $items = [];
        foreach ($itemRows as $item) {
            $twins = [];
            $designProject = null;
            $detailsUrl = null;
            $factories = [];
            $text = null;
            $fullText = null;
            $specsEditUrl = null;
            $uploadUrl = null;

            if ($itemsCount == 1) {
                $twinsGroupsRows = $itemTable->fetchAll(
                    $itemTable->select(true)
                        ->where('item.item_type_id = ?', DbTable\Item\Type::TWINS)
                        ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                        ->where('item_parent_cache.item_id = ?', $item->id)
                );

                foreach ($twinsGroupsRows as $twinsGroup) {
                    $twins[] = [
                        'url' => $controller->url()->fromRoute('twins/group', [
                            'id' => $twinsGroup->id
                        ])
                    ];
                }

                $designCarsRow = $db->fetchRow(
                    $db->select()
                        ->from('item', [
                            'brand_name'    => 'name',
                            'brand_catname' => 'catname'
                        ])
                        ->join('item_parent', 'item.id = item_parent.parent_id', [
                            'brand_item_catname' => 'catname'
                        ])
                        ->where('item_parent.type = ?', DbTable\Item\ParentTable::TYPE_DESIGN)
                        ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.parent_id', 'item_id')
                        ->where('item_parent_cache.item_id = ?', $item->id)
                );
                if ($designCarsRow) {
                    $designProject = [
                        'brand' => $designCarsRow['brand_name'],
                        'url'   => $controller->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item',
                            'brand_catname' => $designCarsRow['brand_catname'],
                            'car_catname'   => $designCarsRow['brand_item_catname']
                        ])
                    ];
                }

                $db = $itemLanguageTable->getAdapter();
                $orderExpr = $db->quoteInto('language = ? desc', $language);
                $itemLanguageRows = $itemLanguageTable->fetchAll([
                    'item_id = ?' => $item['id']
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

                $text = null;
                if ($textIds) {
                    $text = $this->textStorage->getFirstText($textIds);
                }

                $fullText = null;
                if ($fullTextIds) {
                    $fullText = $this->textStorage->getFirstText($fullTextIds);
                }

                if ((bool)$fullText) {
                    foreach ($catalogue->cataloguePaths($item) as $path) {
                        $detailsUrl = $controller->url()->fromRoute('catalogue', [
                            'action'        => 'brand-item',
                            'brand_catname' => $path['brand_catname'],
                            'car_catname'   => $path['car_catname'],
                            'path'          => $path['path']
                        ]);
                        break;
                    }
                }

                // factories
                $factoryRows = $itemTable->fetchAll(
                    $itemTable->select(true)
                        ->where('item.item_type_id = ?', DbTable\Item\Type::FACTORY)
                        ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                        ->where('item_parent_cache.item_id = ?', $item->id)
                );
                foreach ($factoryRows as $factoryRow) {
                    $factories[] = [
                        'name' => $factoryRow->name,
                        'url'  => $controller->url()->fromRoute('factories/factory', [
                            'id' => $factoryRow->id
                        ])
                    ];
                }

                if ($controller->user()->isAllowed('specifications', 'edit')) {
                    $specsEditUrl = $controller->url()->fromRoute('cars/params', [
                        'action'  => 'car-specifications-editor',
                        'item_id' => $item['id']
                    ]);
                }

                if ($controller->user()->logedIn()) {
                    $uploadUrl = $controller->url()->fromRoute('upload/params', [
                        'action'  => 'index',
                        'item_id' => $item['id']
                    ]);
                }
            }

            // alt names
            $altNames = [];
            $altNames2 = [];

            $carLangRows = $itemLanguageTable->fetchAll([
                'item_id = ?' => $item->id
            ]);

            $currentLangName = null;
            $defaultName = $item->name;
            foreach ($carLangRows as $carLangRow) {
                $name = $carLangRow->name;
                if (! isset($altNames[$name])) {
                    $altNames[$carLangRow->name] = [];
                }
                $altNames[$name][] = $carLangRow->language;

                if ($language == $carLangRow->language) {
                    $currentLangName = $name;
                }
            }

            foreach ($altNames as $name => $codes) {
                if (strcmp($name, $currentLangName) != 0) {
                    $altNames2[$name] = $codes;
                }
            }

            if ($currentLangName) {
                unset($altNames2[$currentLangName]);
            }

            // categories
            $categories = [];

            $db = $itemTable->getAdapter();
            $langExpr = $db->quoteInto(
                'item.id = item_language.item_id and item_language.language = ?',
                $language
            );
            $categoryRows = $db->fetchAll(
                $db->select()
                    ->from($itemTable->info('name'), [
                        'id', 'catname', 'begin_year', 'end_year',
                        'name' => new Zend_Db_Expr('IF(LENGTH(item_language.name)>0,item_language.name,item.name)')
                    ])
                    ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->joinLeft('item_language', $langExpr, ['lang_name' => 'name'])
                    ->join('item_parent', 'item.id = item_parent.parent_id', null)
                    ->join(['top_item' => 'item'], 'item_parent.item_id = top_item.id', null)
                    ->where('top_item.item_type_id IN (?)', [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])
                    ->join('item_parent_cache', 'top_item.id = item_parent_cache.parent_id', 'item_id')
                    ->where('item_parent_cache.item_id IN (?)', $item['id'])
                    ->group(['item_parent_cache.item_id', 'item.id'])
            );

            foreach ($categoryRows as $row) {
                $categories[$row['id']] = [
                    'name' => $row,
                    'url'  => $controller->url()->fromRoute('categories', [
                        'action'           => 'category',
                        'category_catname' => $row['catname'],
                    ])
                ];
            }

            $perspective = null;
            if ($isModer) {
                $perspective = [
                    'options' => $multioptions,
                    'url'     => $this->getController()->url()->fromRoute('moder/pictures/params', [
                        'action'     => 'picture-perspective',
                        'picture_id' => $picture->id,
                        'item_id'    => $item->id
                    ]),
                    'value'   => $this->pictureItem->getPerspective($picture->id, $item->id),
                    'name'    => $item->getNameData($language)
                ];
            }

            $hasSpecs = $this->specsService->hasSpecs($item->id);
            $specsUrl = null;
            foreach ($catalogue->cataloguePaths($item) as $path) {
                $specsUrl = $this->getController()->url()->fromRoute('catalogue', [
                    'action'        => 'brand-item-specifications',
                    'brand_catname' => $path['brand_catname'],
                    'car_catname'   => $path['car_catname'],
                    'path'          => $path['path']
                ]);
                break;
            }

            $items[] = [
                'id'            => $item['id'],
                'name'          => $item->getNameData($language),
                'specsUrl'      => $specsUrl,
                //'row'           => $item,
                'hasSpecs'      => $hasSpecs,
                'twins'         => $twins,
                'altNames'      => $altNames2,
                'langName'      => $currentLangName,
                'designProject' => $designProject,
                'categories'    => $categories,
                'detailsUrl'    => $detailsUrl,
                'factories'     => $factories,
                'description'   => $text,
                'text'          => $fullText,
                'perspective'   => $perspective,
                'specsEditUrl'  => $specsEditUrl,
                'uploadUrl'     => $uploadUrl
            ];
        }

        return $items;
    }

    private function picPageEnginesData($picture, $itemIds)
    {
        $controller = $this->getController();
        $catalogue = $controller->catalogue();

        $language = $controller->language();

        $itemTable = $catalogue->getItemTable();
        $itemModel = new \Application\Model\Item();

        $engineRows = [];
        if ($itemIds) {
            $engineRows = $itemTable->fetchAll([
                'id IN (?)'        => $itemIds,
                'item_type_id = ?' => DbTable\Item\Type::ENGINE
            ]);
        }

        $engines = [];
        foreach ($engineRows as $engineRow) {
            $vehicles = [];

            $vehicleIds = $itemModel->getEngineVehiclesGroups($engineRow->id);
            if ($vehicleIds) {
                $carRows = $itemTable->fetchAll([
                    'id in (?)' => $vehicleIds
                ], $catalogue->itemOrdering());

                foreach ($carRows as $carRow) {
                    $cataloguePaths = $catalogue->cataloguePaths($carRow);

                    foreach ($cataloguePaths as $cPath) {
                        $vehicles[] = [
                            'name' => $controller->car()->formatName($carRow, $language),
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

            $specsUrl = false;
            $hasSpecs = $this->specsService->hasSpecs($engineRow->id);

            if ($hasSpecs) {
                $cataloguePaths = $catalogue->cataloguePaths($engineRow);

                foreach ($cataloguePaths as $path) {
                    $specsUrl = $controller->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-specifications',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path']
                    ]);
                    break;
                }
            }

            $specsEditUrl = null;
            if ($controller->user()->isAllowed('specifications', 'edit')) {
                $specsEditUrl = $controller->url()->fromRoute('cars/params', [
                    'action'  => 'car-specifications-editor',
                    'item_id' => $engineRow->id
                ]);
            }

            $engines[] = [
                'name'         => $engineRow->name,
                'vehicles'     => $vehicles,
                'hasSpecs'     => $hasSpecs,
                'specsUrl'     => $specsUrl,
                'specsEditUrl' => $specsEditUrl
            ];
        }

        return $engines;
    }

    private function picPageFactoriesData($picture)
    {
        $controller = $this->getController();
        $catalogue = $controller->catalogue();

        $language = $controller->language();

        $itemTable = $catalogue->getItemTable();

        $factories = $itemTable->fetchAll(
            $itemTable->select(true)
                ->where('item.item_type_id = ?', DbTable\Item\Type::FACTORY)
                ->join('picture_item', 'item.id = picture_item.item_id', null)
                ->where('picture_item.picture_id = ?', $picture['id'])
        );

        $result = [];

        foreach ($factories as $factory) {

            $factoryCars = [];
            $factoryCarsMore = false;

            $carIds = $factory->getRelatedCarGroupId();
            if ($carIds) {
                $itemTable = $catalogue->getItemTable();

                $carRows = $itemTable->fetchAll([
                    'id in (?)' => $carIds
                ], $catalogue->itemOrdering());

                $limit = 10;

                if (count($carRows) > $limit) {
                    $rows = [];
                    foreach ($carRows as $carRow) {
                        $rows[] = $carRow;
                    }
                    $carRows = array_slice($rows, 0, $limit);
                    $factoryCarsMore = true;
                }

                foreach ($carRows as $carRow) {
                    $cataloguePaths = $catalogue->cataloguePaths($carRow);

                    foreach ($cataloguePaths as $cPath) {
                        $factoryCars[] = [
                            'name' => $controller->car()->formatName($carRow, $language),
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

            $result[] = [
                'id'        => $factory['id'],
                'items'     => $factoryCars,
                'itemsMore' => $factoryCarsMore,
            ];
        }

        return $result;
    }

    public function picPageData($picture, $picSelect, $brandIds = [], array $options = [])
    {
        $options = array_replace([
            'paginator' => [
                'route'     => 'picture/picture',
                'urlParams' => []
            ]
        ], $options);

        $controller = $this->getController();
        $catalogue = $controller->catalogue();
        $imageStorage = $controller->imageStorage();

        $isModer = $controller->user()->inheritsRole('moder');

        $db = $this->pictureTable->getAdapter();

        $items = [];

        $language = $controller->language();

        $brandIds = $db->fetchCol(
            $db->select()
                ->from('item', 'id')
                ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', null)
                ->where('picture_item.picture_id = ?', $picture->id)
        );

        // links
        $ofLinks = [];
        $linksTable = new DbTable\BrandLink();
        if (count($brandIds)) {
            $links = $linksTable->fetchAll(
                $linksTable->select(true)
                    ->where('item_id in (?)', $brandIds)
                    ->where('type = ?', 'official')
            );
            foreach ($links as $link) {
                $ofLinks[$link->id] = $link;
            }
        }

        $replacePicture = null;
        if ($picture->replace_picture_id) {
            $replacePictureRow = $this->pictureTable->find($picture->replace_picture_id)->current();

            $replacePicture = $controller->pic()->href($replacePictureRow->toArray());

            if ($replacePictureRow->status == DbTable\Picture::STATUS_REMOVING) {
                if (! $controller->user()->inheritsRole('moder')) {
                    $replacePicture = null;
                }
            }
        }

        $moderLinks = [];
        if ($isModer) {
            $moderLinks = $this->getModerLinks($picture);
        }

        $userTable = new UserTable();

        $moderVotes = [];
        foreach ($picture->findDependentRowset(DbTable\Picture\ModerVote::class) as $moderVote) {
            $moderVotes[] = [
                'vote'   => $moderVote->vote,
                'reason' => $moderVote->reason,
                'user'   => $userTable->find($moderVote->user_id)->current()
            ];
        }

        $image = $imageStorage->getImage($picture->image_id);
        $sourceUrl = $image ? $image->getSrc() : null;

        $preview = $imageStorage->getFormatedImage($picture->getFormatRequest(), 'picture-medium');
        $previewUrl = $preview ? $preview->getSrc() : null;


        $paginator = false;
        $pageNumbers = false;

        if ($picSelect) {
            $paginator = new \Zend\Paginator\Paginator(
                new Zend1DbTableSelect($picSelect)
            );

            $total = $paginator->getTotalItemCount();

            if ($total < 500) {
                $db = $this->pictureTable->getAdapter();

                $paginatorPictures = $db->fetchAll(
                    $db->select()
                        ->from(['_pic' => new Zend_Db_Expr('('.$picSelect->assemble() .')')], ['id', 'identity'])
                );

                $pageNumber = 0;
                foreach ($paginatorPictures as $n => $p) {
                    if ($p['id'] == $picture->id) {
                        $pageNumber = $n + 1;
                        break;
                    }
                }

                $paginator
                    ->setItemCountPerPage(1)
                    ->setPageRange(15)
                    ->setCurrentPageNumber($pageNumber);

                $pages = $paginator->getPages();

                $pageNumbers = $pages->pagesInRange;
                if (isset($pages->previous)) {
                    $pageNumbers[] = $pages->previous;
                }
                if (isset($pages->next)) {
                    $pageNumbers[] = $pages->next;
                }

                $pageNumbers = array_unique($pageNumbers);
                $pageNumbers = array_combine($pageNumbers, $pageNumbers);

                foreach ($pageNumbers as $page => &$val) {
                    $pic = $paginatorPictures[$page - 1];
                    $val = $controller->url()->fromRoute(
                        $options['paginator']['route'],
                        array_replace($options['paginator']['urlParams'], [
                            'picture_id' => $pic['identity']
                        ])
                    );
                }
                unset($val);
            } else {
                $paginator = false;
            }
        }

        $names = $this->pictureTable->getNameData([$picture->toArray()], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$picture->id];

        $mTable = new DbTable\Modification();
        $mRows = $mTable->fetchAll(
            $mTable->select(true)
                ->join('modification_picture', 'modification.id = modification_picture.modification_id', null)
                ->where('modification_picture.picture_id = ?', $picture['id'])
                ->order('modification.name')
        );

        $modifications = [];
        $itemTable = new DbTable\Item();
        foreach ($mRows as $mRow) {
            $url = null;
            $carRow = $itemTable->find($mRow->item_id)->current();
            if ($carRow) {
                $itemParentTable = new DbTable\Item\ParentTable();
                $paths = $itemParentTable->getPaths($carRow->id, [
                    'breakOnFirst' => true
                ]);
                if (count($paths) > 0) {
                    $path = $paths[0];

                    $url = $controller->url()->fromRoute('catalogue', [
                        'action'        => 'brand-item-pictures',
                        'brand_catname' => $path['brand_catname'],
                        'car_catname'   => $path['car_catname'],
                        'path'          => $path['path'],
                        'mod'           => $mRow->id
                    ]);
                }
            }

            $modifications[] = [
                'name' => $mRow->name,
                'url'  => $url
            ];
        }

        $copyrights = null;
        if ($picture->copyrights_text_id) {
            $copyrights = $this->textStorage->getText($picture->copyrights_text_id);
        }

        $point = null;
        if ($picture->point) {
            $point = \geoPHP::load(substr($picture->point, 4), 'wkb');
        }

        $itemIds = $this->pictureItem->getPictureItems($picture['id']);

        $data = [
            'id'                => $picture['id'],
            'point'             => $point,
            'copyrights'        => $copyrights,
            'identity'          => $picture['identity'],
            'name'              => $name,
            'picture'           => $picture,
            'owner'             => $picture->findParentRow(UserTable::class, 'Owner'),
            'addDate'           => $picture->getDateTime('add_date'),
            'ofLinks'           => $ofLinks,
            'moderVotes'        => $moderVotes,
            'sourceUrl'         => $sourceUrl,
            'previewUrl'        => $previewUrl,
            'replacePicture'    => $replacePicture,
            'gallery'           => [
                'current' => $picture->id
            ],
            'paginator'         => $paginator,
            'paginatorPictures' => $pageNumbers,
            'moderLinks'        => $moderLinks,
            'modifications'     => $modifications,
            'pictureVote'       => $this->getController()->pictureVote($picture->id, [
                'hideVote' => true
            ]),
            //'picturePerspectives' => $picturePerspectives,
            'items'             => $this->picPageItemsData($picture, $itemIds),
            'engines'           => $this->picPageEnginesData($picture, $itemIds),
            'factories'         => $this->picPageFactoriesData($picture)
        ];

        // refresh views count
        $views = new DbTable\Picture\View();
        $views->inc($picture);

        return $data;
    }

    private function getModerLinks($picture)
    {
        $controller = $this->getController();
        $language = $controller->language();

        $links = [];
        $links[$controller->url()->fromRoute('moder/pictures/params', [
            'action'     => 'picture',
            'picture_id' => $picture->id
        ])] = sprintf($this->translator->translate('moder/picture/edit-picture-%s'), $picture->id);

        $carIds = $this->pictureItem->getPictureItems($picture['id']);
        if ($carIds) {
            $vehicleTable = new DbTable\Item();
            $brandModel = new BrandModel();

            foreach ($vehicleTable->find($carIds) as $car) {
                $url = $controller->url()->fromRoute('moder/cars/params', [
                    'action'  => 'car',
                    'item_id' => $car->id
                ]);
                $links[$url] = sprintf(
                    $this->translator->translate('moder/picture/edit-vehicle-%s'),
                    $controller->car()->formatName($car, $language)
                );


                $brands = $brandModel->getList(['language' => $language], function ($select) use ($car) {
                    $select
                        ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                        ->where('item_parent_cache.item_id = ?', $car->id)
                        ->group('item.id');
                });

                foreach ($brands as $brand) {
                    $url = $controller->url()->fromRoute('moder/cars/params', [
                        'action'  => 'car',
                        'item_id' => $brand['id']
                    ]);
                    $links[$url] = sprintf(
                        $this->translator->translate('moder/picture/edit-brand-%s'),
                        $brand['name']
                    );
                }
            }
        }
        return $links;
    }


    public function gallery2(Zend_Db_Table_Select $picSelect, array $options = [])
    {
        $defaults = [
            'page'      => 1,
            'pictureId' => null,
            'route'     => null,
            'urlParams' => []
        ];
        $options = array_replace($defaults, $options);

        $itemsPerPage = 10;

        $galleryStatuses = [DbTable\Picture::STATUS_ACCEPTED, DbTable\Picture::STATUS_NEW];

        $gallery = [];

        $controller = $this->getController();
        $catalogue = $controller->catalogue();
        $imageStorage = $controller->imageStorage();

        $language = $controller->language();

        if ($options['pictureId']) {
            // look for page of that picture
            $select = clone $picSelect;

            $select
                ->setIntegrityCheck(false)
                ->reset(Zend_Db_Select::COLUMNS)
                ->columns([
                    'pictures.id'
                ]);

            $col = $select->getAdapter()->fetchCol($select);
            foreach ($col as $index => $id) {
                if ($id == $options['pictureId']) {
                    $options['page'] = ceil(($index + 1) / $itemsPerPage);
                    break;
                }
            }
        }

        $select = clone $picSelect;

        $select
            ->setIntegrityCheck(false)
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns([
                'pictures.id', 'pictures.identity', 'pictures.name',
                'pictures.width', 'pictures.height',
                'pictures.crop_left', 'pictures.crop_top',
                'pictures.crop_width', 'pictures.crop_height',
                'pictures.image_id', 'pictures.filesize'
            ])
            ->joinLeft(
                ['ct' => 'comment_topic'],
                'ct.type_id = :type_id and ct.item_id = pictures.id',
                'messages'
            )
            ->bind([
                'type_id' => DbTable\Comment\Message::PICTURES_TYPE_ID
            ]);

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbSelect($select)
        );

        $count = $paginator->getTotalItemCount();

        $paginator = new \Zend\Paginator\Paginator(
            new \Zend\Paginator\Adapter\NullFill($count)
        );

        $paginator
            ->setItemCountPerPage($itemsPerPage)
            ->setCurrentPageNumber($options['page']);

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $rows = $select->getAdapter()->fetchAll($select);



        // prefetch
        $ids = [];
        $fullRequests = [];
        $cropRequests = [];
        $imageIds = [];
        foreach ($rows as $idx => $picture) {
            $request = DbTable\Picture\Row::buildFormatRequest($picture);
            $fullRequests[$idx] = $request;
            if (DbTable\Picture\Row::checkCropParameters($picture)) {
                $cropRequests[$idx] = $request;
            }
            $ids[] = (int)$picture['id'];
            $imageIds[] = (int)$picture['image_id'];
        }

        // images
        $images = $imageStorage->getImages($imageIds);
        $fullImagesInfo = $imageStorage->getFormatedImages($fullRequests, 'picture-gallery-full');
        $cropImagesInfo = $imageStorage->getFormatedImages($cropRequests, 'picture-gallery');


        // names
        $names = $this->pictureTable->getNameData($rows, [
            'language' => $language
        ]);

        // comments
        $userId = null;
        if ($controller->user()->logedIn()) {
            $userId = $controller->user()->get()->id;
        }

        if ($userId) {
            $ctTable = new DbTable\Comment\Topic();
            $newMessages = $ctTable->getNewMessages(
                DbTable\Comment\Message::PICTURES_TYPE_ID,
                $ids,
                $userId
            );
        }

        $route = $options['route'] ? $options['route'] : null;


        foreach ($rows as $idx => $row) {
            $imageId = (int)$row['image_id'];

            if (! $imageId) {
                continue;
            }

            $image = isset($images[$imageId]) ? $images[$imageId] : null;
            if (! $image) {
                continue;
            }

            $id = (int)$row['id'];

            $sUrl = $image->getSrc();

            if (DbTable\Picture\Row::checkCropParameters($row)) {
                $crop = isset($cropImagesInfo[$idx]) ? $cropImagesInfo[$idx]->toArray() : null;

                $crop['crop'] = [
                    'left'   => $row['crop_left'] / $image->getWidth(),
                    'top'    => $row['crop_top'] / $image->getHeight(),
                    'width'  => $row['crop_width'] / $image->getWidth(),
                    'height' => $row['crop_height'] / $image->getHeight(),
                ];
            } else {
                $crop = null;
            }

            $full = isset($fullImagesInfo[$idx]) ? $fullImagesInfo[$idx]->toArray() : null;

            $msgCount = $row['messages'];
            $newMsgCount = 0;
            if ($userId) {
                $newMsgCount = isset($newMessages[$id]) ? $newMessages[$id] : $msgCount;
            }

            $name = isset($names[$id]) ? $names[$id] : null;
            $name = $this->pictureNameFormatter->format($name, $language);

            $reuseParams = isset($options['reuseParams']) && $options['reuseParams'];
            $url = $controller->url()->fromRoute($route, array_replace($options['urlParams'], [
                'picture_id' => $row['identity'],
                'gallery'    => null,
            ]), [], $reuseParams);

            $itemsData = $this->pictureItem->getData([
                'picture'      => $row['id'],
                'onlyWithArea' => true
            ]);

            $itemTable = new DbTable\Item();

            $areas = [];
            foreach ($itemsData as $pictureItem) {
                $item = $itemTable->find($pictureItem['item_id'])->current();
                $areas[] = [
                    'area' => [
                        'left'   => $pictureItem['area'][0] / $image->getWidth(),
                        'top'    => $pictureItem['area'][1] / $image->getHeight(),
                        'width'  => $pictureItem['area'][2] / $image->getWidth(),
                        'height' => $pictureItem['area'][3] / $image->getHeight(),
                    ],
                    'name' => $this->itemNameFormatter->formatHtml($item->getNameData($language), $language)
                ];
            }

            $gallery[] = [
                'id'          => $id,
                'url'         => $url,
                'sourceUrl'   => $sUrl,
                'crop'        => $crop,
                'full'        => $full,
                'messages'    => $msgCount,
                'newMessages' => $newMsgCount,
                'name'        => $name,
                'filesize'    => $row['filesize'], //$view->fileSize($row['filesize'])
                'areas'       => $areas
            ];
        }

        return [
            'page'  => $paginator->getCurrentPageNumber(),
            'pages' => $paginator->count(),
            'count' => $paginator->getTotalItemCount(),
            'items' => $gallery
        ];
    }

    public function name($pictureRow, $language)
    {
        $names = $this->pictureTable->getNameData([$pictureRow->toArray()], [
            'language' => $language,
            'large'    => true
        ]);
        $name = $names[$pictureRow->id];

        return $this->pictureNameFormatter->format($name, $language);
    }
}
