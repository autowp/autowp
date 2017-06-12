<?php

namespace Application\Hydrator\Api;

use Autowp\User\Model\DbTable\User;

use Application\ItemNameFormatter;
use Application\Model\Catalogue;
use Application\Model\DbTable;
use Application\Model\Item as ItemModel;
use Application\Service\SpecificationsService;

use Zend_Db_Expr;

class ItemHydrator extends RestHydrator
{
    /**
     * @var int|null
     */
    private $userId = null;

    private $userRole = null;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    private $router;

    /**
     * @var DbTable\Spec
     */
    private $specTable;

    /**
     * @var DbTable\Item
     */
    private $itemTable;

    /**
     * @var Catalogue
     */
    private $catalogue;

    private $picHelper;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var \Application\Model\Item
     */
    private $itemModel;

    /**
     * @var DbTable\Item\Language
     */
    private $itemLanguageTable;

    /**
     * @var \Autowp\TextStorage\Service
     */
    private $textStorage;

    /**
     * @return DbTable\Spec
     */
    private function getSpecTable()
    {
        return $this->specTable
            ? $this->specTable
            : $this->specTable = new DbTable\Spec();
    }

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);
        $this->router = $serviceManager->get('HttpRouter');

        $this->itemLanguageTable = new DbTable\Item\Language();
        $this->itemParentTable = new DbTable\Item\ParentTable();
        $this->itemTable = new DbTable\Item();
        $this->itemModel = new \Application\Model\Item();

        $this->acl = $serviceManager->get(\Zend\Permissions\Acl\Acl::class);
        $this->textStorage = $serviceManager->get(\Autowp\TextStorage\Service::class);

        $this->catalogue = $serviceManager->get(Catalogue::class);

        $this->picHelper = $serviceManager->get('ControllerPluginManager')->get('pic');

        $this->specsService = $serviceManager->get(SpecificationsService::class);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('brands', $strategy);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('categories', $strategy);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('twins_groups', $strategy);

        $strategy = new Strategy\PreviewPictures($serviceManager);
        $this->addStrategy('preview_pictures', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @return RestHydrator
     * @throws \Zend\Hydrator\Exception\InvalidArgumentException
     */
    public function setOptions($options)
    {
        parent::setOptions($options);

        if ($options instanceof \Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new \Zend\Hydrator\Exception\InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        return $this;
    }

    /**
     * @param int|null $userId
     * @return Comment
     */
    public function setUserId($userId = null)
    {
        $this->userId = $userId;

        //$this->getStrategy('content')->setUser($user);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    private function getNameData(array $object, $language = 'en')
    {
        if (! is_string($language)) {
            throw new \Exception('`language` is not string');
        }

        $name = $this->itemModel->getName($object['id'], $language);

        $spec = null;
        $specFull = null;
        if ($object['spec_id']) {
            $specRow = $this->getSpecTable()->find($object['spec_id'])->current();
            if ($specRow) {
                $spec = $specRow->short_name;
                $specFull = $specRow->name;
            }
        }

        $result = [
            'begin_model_year' => $object['begin_model_year'],
            'end_model_year'   => $object['end_model_year'],
            'spec'             => $spec,
            'spec_full'        => $specFull,
            'body'             => $object['body'],
            'name'             => $name,
            'begin_year'       => $object['begin_year'],
            'end_year'         => $object['end_year'],
            'today'            => $object['today'],
            'begin_month'      => $object['begin_month'],
            'end_month'        => $object['end_month']
        ];

        return $result;
    }

    public function extract($object)
    {
        $nameData = $this->getNameData($object, $this->language);

        $listBuilder = new \Application\Model\Item\ListBuilder([
            'catalogue'    => $this->catalogue,
            'router'       => $this->router,
            'picHelper'    => $this->picHelper,
            'specsService' => $this->specsService
        ]);

        $result = [
            'id'           => (int)$object['id'],
            'item_type_id' => (int)$object['item_type_id']
        ];

        if ($this->filterComposite->filter('moder_url')) {
            $result['moder_url'] = $this->router->assemble([
                'action'  => 'car',
                'item_id' => $object['id']
            ], [
                'name' => 'moder/cars/params'
            ]);
        }

        if ($this->filterComposite->filter('upload_url')) {
            $result['upload_url'] = $this->router->assemble([
                'action'  => 'index',
                'item_id' => $object['id']
            ], [
                'name' => 'upload/params'
            ]);
        }

        if ($this->filterComposite->filter('specs_url')) {
            $result['specs_url'] = $listBuilder->getSpecificationsUrl($object);
        }

        if ($this->filterComposite->filter('spec_editor_url')) {
            if ($this->isAllowed('specifications', 'edit')) {
                $result['spec_editor_url'] = $this->router->assemble([
                    'action'  => 'car-specifications-editor',
                    'item_id' => $object['id']
                ], [
                    'name' => 'cars/params'
                ]);
            }
        }

        if ($this->filterComposite->filter('catname')) {
            $result['catname'] = $object['catname'];
        }

        $totalPictures = null;
        $pictures = [];
        $cFetcher = null;
        $showTotalPictures = $this->filterComposite->filter('total_pictures');
        $showMorePicturesUrl = $this->filterComposite->filter('more_pictures_url');
        $showPreviewPictures = $this->filterComposite->filter('preview_pictures');
        $onlyExactlyPictures = false;

        if ($showTotalPictures || $showMorePicturesUrl || $showPreviewPictures) {
            $cFetcher = new \Application\Model\Item\PerspectivePictureFetcher([
                'type'                 => null,
                'onlyExactlyPictures'  => $onlyExactlyPictures,
                'dateSort'             => false,
                'disableLargePictures' => false,
                'perspectivePageId'    => null,
                'onlyChilds'           => []
            ]);

            $carsTotalPictures = $cFetcher->getTotalPictures([$object['id']], $onlyExactlyPictures);
            $totalPictures = isset($carsTotalPictures[$object['id']]) ? (int)$carsTotalPictures[$object['id']] : 0;
        }

        if ($showPreviewPictures) {
            $pictures = $cFetcher->fetch($object, [
                'totalPictures' => $totalPictures
            ]);

            $largeFormat = false;
            foreach ($pictures as &$picture) {
                if ($picture) {
                    if (isset($picture['isVehicleHood']) && $picture['isVehicleHood']) {
                        $url = $this->picHelper->href($picture['row']);
                    } else {
                        $url = $listBuilder->getPictureUrl($object, $picture['row']);
                    }
                    $picture['url'] = $url;
                    if ($picture['format'] == 'picture-thumb-medium') {
                        $largeFormat = true;
                    }
                }
            }
            unset($picture);

            $result['preview_pictures'] = $this->extractValue('preview_pictures', $pictures);
        }

        if ($showTotalPictures) {
            $result['total_pictures'] = $totalPictures;
        }

        if ($showMorePicturesUrl) {
            if (count($pictures) < $totalPictures) {
                $result['more_pictures_url'] = $listBuilder->getPicturesUrl($object);
            } else {
                $result['more_pictures_url'] = null;
            }
        }

        if ($this->filterComposite->filter('childs_count')) {
            if (isset($object['childs_count'])) {
                $result['childs_count'] = (int)$object['childs_count'];
            } else {
                $db = $this->itemParentTable->getAdapter();
                $result['childs_count'] = (int)$db->fetchOne(
                    $db->select()
                        ->from('item_parent', [new Zend_Db_Expr('count(1)')])
                        ->where('parent_id = ?', $object['id'])
                );
            }
        }

        if ($this->filterComposite->filter('name_default')) {
            $name = $this->itemModel->getName($object['id'], 'xx');
            $result['name_default'] = $nameData['name'] == $name ? null : $name;
        }

        if ($this->filterComposite->filter('name_text')) {
            $result['name_text'] = $this->itemNameFormatter->format(
                $nameData,
                $this->language
            );
        }

        if ($this->filterComposite->filter('name_html')) {
            $result['name_html'] = $this->itemNameFormatter->formatHtml(
                $nameData,
                $this->language
            );
        }

        if ($this->filterComposite->filter('brands')) {
            $rows = $this->itemTable->fetchAll(
                $this->itemTable->select(true)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                    ->where('item_parent_cache.item_id = ?', $object['id'])
                    ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                    ->group('item.id')
            );

            $result['brands'] = $this->extractValue('brands', $rows->toArray());
        }

        if ($this->filterComposite->filter('categories')) {
            $rows = $this->itemTable->fetchAll(
                $this->itemTable->select(true)
                    ->where('item.item_type_id = ?', DbTable\Item\Type::CATEGORY)
                    ->join('item_parent', 'item.id = item_parent.parent_id', null)
                    ->join(['top_item' => 'item'], 'item_parent.item_id = top_item.id', null)
                    ->where('top_item.item_type_id IN (?)', [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])
                    ->join('item_parent_cache', 'top_item.id = item_parent_cache.parent_id', null)
                    ->where('item_parent_cache.item_id = ?', $object['id'])
                    ->group(['item.id'])
            );

            $result['categories'] = $this->extractValue('categories', $rows->toArray());
        }

        if ($this->filterComposite->filter('twins_groups')) {
            $rows = $this->itemTable->fetchAll(
                $this->itemTable->select(true)
                    ->where('item.item_type_id = ?', DbTable\Item\Type::TWINS)
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                    ->where('item_parent_cache.item_id = ?', $object['id'])
                    ->group('item.id')
            );

            $result['twins_groups'] = $this->extractValue('twins_groups', $rows->toArray());
        }

        if ($this->filterComposite->filter('url')) {
            $url = null;
            switch ($object['item_type_id']) {
                case DbTable\Item\Type::CATEGORY:
                    $url = $this->router->assemble([
                        'action'           => 'category',
                        'category_catname' => $object['catname'],
                    ], [
                        'name' => 'categories'
                    ]);
                    break;
                case DbTable\Item\Type::TWINS:
                    $url = $this->router->assemble([
                        'id' => $object['id'],
                    ], [
                        'name' => 'twins/group'
                    ]);
                    break;

                case DbTable\Item\Type::ENGINE:
                case DbTable\Item\Type::VEHICLE:
                    $url = $listBuilder->getDetailsUrl($object);
                    break;
            }

            $result['url'] = $url;
        }

        if ($this->filterComposite->filter('produced')) {
            $result['produced'] = (int)$object['produced'];
            $result['produced_exactly'] = (bool)$object['produced_exactly'];
        }

        if ($this->filterComposite->filter('design')) {
            $db = $this->itemParentTable->getAdapter();
            $designRow = $db->fetchRow(
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
                    ->where('item_parent_cache.item_id = ?', $object['id'])
            );
            if ($designRow) {
                $result['design'] = [
                    'brand_name' => $designRow['brand_name'],
                    'url'        => $this->router->assemble([
                        'action'        => 'brand-item',
                        'brand_catname' => $designRow['brand_catname'] ? $designRow['brand_catname'] : 'test',
                        'car_catname'   => $designRow['brand_item_catname']
                    ], [
                        'name' => 'catalogue'
                    ])
                ];
            } else {
                $result['design'] = null;
            }
        }

        if ($this->filterComposite->filter('engine_vehicles')) {
            $vehiclesOnEngine = [];
            if ($object['item_type_id'] == DbTable\Item\Type::ENGINE) {
                $result['engine_vehicles'] = $this->getVehiclesOnEngine($object);
            }
        }

        $showDescription = $this->filterComposite->filter('description');
        $showHasText = $this->filterComposite->filter('has_text');

        $textIds = [];
        $fullTextIds = [];

        if ($showDescription || $showHasText) {
            $db = $this->itemLanguageTable->getAdapter();
            $orderExpr = $db->quoteInto('language = ? desc', $this->language);
            $itemLanguageRows = $this->itemLanguageTable->fetchAll([
                'item_id = ?' => $object['id']
            ], new \Zend_Db_Expr($orderExpr));

            foreach ($itemLanguageRows as $itemLanguageRow) {
                if ($itemLanguageRow->text_id) {
                    $textIds[] = $itemLanguageRow->text_id;
                }
                if ($itemLanguageRow->full_text_id) {
                    $fullTextIds[] = $itemLanguageRow->full_text_id;
                }
            }
        }

        if ($showHasText) {
            $text = null;
            if ($fullTextIds) {
                $text = $this->textStorage->getFirstText($fullTextIds);
            }

            $result['has_text'] = (bool)$text;
        }

        if ($showDescription) {
            $description = null;
            if ($textIds) {
                $description = $this->textStorage->getFirstText($textIds);
            }

            $result['description'] = $description;
        }

        return $result;
    }

    public function hydrate(array $data, $object)
    {
        throw new \Exception("Not supported");
    }

    private function getUserRole()
    {
        if (! $this->userId) {
            return null;
        }

        if (! $this->userRole) {
            $table = new User();
            $db = $table->getAdapter();
            $this->userRole = $db->fetchOne(
                $db->select()
                    ->from($table->info('name'), ['role'])
                    ->where('id = ?', $this->userId)
            );
        }

        return $this->userRole;
    }

    private function isAllowed($resource, $privilege)
    {
        $role = $this->getUserRole();
        if (! $role) {
            return false;
        }
        return $this->acl->isAllowed($role, $resource, $privilege);
    }

    private function getVehiclesOnEngine($engine)
    {
        $result = [];

        $ids = $this->itemModel->getEngineVehiclesGroups($engine['id'], [
            'groupJoinLimit' => 3
        ]);

        if ($ids) {
            $rows = $this->itemTable->fetchAll([
                'id in (?)' => $ids
            ], $this->catalogue->itemOrdering());
            foreach ($rows as $row) {
                $cataloguePaths = $this->catalogue->getCataloguePaths($row['id']);
                foreach ($cataloguePaths as $cPath) {
                    $result[] = [
                        'name_html' => $this->itemNameFormatter->formatHtml(
                            $row->getNameData($this->language),
                            $this->language
                        ),
                        'url'  => $this->router->assemble([
                            'action'        => 'brand-item',
                            'brand_catname' => $cPath['brand_catname'],
                            'car_catname'   => $cPath['car_catname'],
                            'path'          => $cPath['path']
                        ], [
                            'name' => 'catalogue'
                        ])
                    ];
                    break;
                }
            }
        }

        return $result;
    }
}
