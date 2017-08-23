<?php

namespace Application\Hydrator\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\User\Model\User;

use Application\ItemNameFormatter;
use Application\Model\Catalogue;
use Application\Model\ItemParent;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\UserItemSubscribe;
use Application\Service\SpecificationsService;

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
     * @var TableGateway
     */
    private $specTable;

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
     * @var \Autowp\TextStorage\Service
     */
    private $textStorage;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var TableGateway
     */
    private $linkTable;

    /**
     * @var SpecificationsService
     */
    private $specificationsService;

    /**
     * @var UserItemSubscribe
     */
    private $userItemSubscribe;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var ItemParent
     */
    private $itemParent;

    /**
     * @var User
     */
    private $userModel;

    public function __construct(
        $serviceManager
    ) {
        parent::__construct();

        $tables = $serviceManager->get('TableManager');
        $this->picture = $serviceManager->get(Picture::class);
        $this->linkTable = $tables->get('links');
        $this->specTable = $tables->get('spec');

        $this->userModel = $serviceManager->get(\Autowp\User\Model\User::class);

        $this->perspective = $serviceManager->get(Perspective::class);
        $this->userItemSubscribe = $serviceManager->get(UserItemSubscribe::class);

        $this->specificationsService = $serviceManager->get(SpecificationsService::class);

        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);
        $this->router = $serviceManager->get('HttpRouter');

        $this->itemModel = $serviceManager->get(\Application\Model\Item::class);
        $this->itemParent = $serviceManager->get(ItemParent::class);

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

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('logo', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('brandicon', $strategy);
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

    private function getNameData($object, string $language = 'en'): array
    {
        if (! is_string($language)) {
            throw new \Exception('`language` is not string');
        }

        $name = $this->itemModel->getName($object['id'], $language);

        $spec = null;
        $specFull = null;
        if ($object['spec_id']) {
            $specRow = $this->specTable->select(['id' => (int)$object['spec_id']])->current();
            if ($specRow) {
                $spec = $specRow['short_name'];
                $specFull = $specRow['name'];
            }
        }

        return [
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
    }

    private function getCountBySelect(Sql\Select $select, TableGateway $table): int
    {
        $select->columns(['count' => new Sql\Expression('count(1)')]);
        $row = $table->selectWith($select)->current();
        return $row ? (int)$row['count'] : 0;
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

        if ($this->filterComposite->filter('body')) { // only for moders
            $result['body'] = (string)$object['body'];
        }

        if ($this->filterComposite->filter('is_concept')) { // only for moders
            if ($object['is_concept_inherit']) {
                $result['is_concept'] = 'inherited';
            } else {
                $result['is_concept'] = (bool)$object['is_concept'];
            }
        }

        if ($this->filterComposite->filter('spec_id')) { // only for moders

            if ($object['spec_inherit']) {
                $result['spec_id'] = 'inherited';
            } else {
                $value = (int)$object['spec_id'];
                $result['spec_id'] = $value > 0 ? $value : null;
            }
        }

        if ($this->filterComposite->filter('is_group')) { // only for moders
            $result['is_group'] = (bool)$object['is_group'];
        }

        if ($this->filterComposite->filter('begin_model_year')) { // only for moders
            $value = (int)$object['begin_model_year'];
            $result['begin_model_year'] = $value > 0 ? $value : null;
        }

        if ($this->filterComposite->filter('end_model_year')) { // only for moders
            $value = (int)$object['end_model_year'];
            $result['end_model_year'] = $value > 0 ? $value : null;
        }

        if ($this->filterComposite->filter('begin_year')) { // only for moders
            $value = (int)$object['begin_year'];
            $result['begin_year'] = $value > 0 ? $value : null;
        }

        if ($this->filterComposite->filter('begin_month')) { // only for moders
            $value = (int)$object['begin_month'];
            $result['begin_month'] = $value > 0 ? $value : null;
        }

        if ($this->filterComposite->filter('end_year')) { // only for moders
            $value = (int)$object['end_year'];
            $result['end_year'] = $value > 0 ? $value : null;
        }

        if ($this->filterComposite->filter('end_month')) { // only for moders
            $value = (int)$object['end_month'];
            $result['end_month'] = $value > 0 ? $value : null;
        }

        if ($this->filterComposite->filter('today')) { // only for moders
            $result['today'] = $object['today'] === null ? null : (bool)$object['today'];
        }

        if ($this->filterComposite->filter('subscription') && $this->userId) { // only for moders
            $result['subscription'] = $this->userItemSubscribe->isSubscribed((int)$this->userId, (int)$object['id']);
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

        if ($this->filterComposite->filter('full_name')) {
            $result['full_name'] = $object['full_name'];
        }

        if ($this->filterComposite->filter('catname')) {
            $result['catname'] = $object['catname'];
        }

        $showLat = $this->filterComposite->filter('lat');
        $showLng = $this->filterComposite->filter('lng');

        if ($showLat || $showLng) {
            $point = $this->itemModel->getPoint($object['id']);

            if ($showLat) {
                $result['lat'] = $point ? $point->y() : null;
            }

            if ($showLat) {
                $result['lng'] = $point ? $point->x() : null;
            }
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
                'pictureModel'         => $this->picture,
                'itemModel'            => $this->itemModel,
                'perspective'          => $this->perspective,
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

        if ($this->filterComposite->filter('pictures_count')) {
            $result['pictures_count'] = $this->picture->getCount([
                'item' => $object['id']
            ]);
        }

        if ($this->filterComposite->filter('specifications_count')) {
            $result['specifications_count'] = $this->specificationsService->getSpecsCount($object['id']);
        }

        if ($this->filterComposite->filter('links_count')) {
            $select = new Sql\Select($this->linkTable->getTable());
            $select->where(['item_id' => $object['id']]);
            $result['links_count'] = $this->getCountBySelect($select, $this->linkTable);
        }

        if ($this->filterComposite->filter('childs_count')) {
            if (isset($object['childs_count'])) {
                $result['childs_count'] = (int)$object['childs_count'];
            } else {
                $result['childs_count'] = $this->itemParent->getChildItemsCount($object['id']);
            }
        }

        if ($this->filterComposite->filter('parents_count')) {
            $result['parents_count'] = $this->itemParent->getParentItemsCount($object['id']);
        }

        if ($this->filterComposite->filter('item_language_count')) {
            $result['item_language_count'] = $this->itemModel->getUsedLanguagesCount($object['id']);
        }

        if ($this->filterComposite->filter('engine_vehicles_count')) {
            $select = new Sql\Select($this->itemModel->getTable()->getTable());
            $select->where(['engine_item_id' => $object['id']]);
            $result['engine_vehicles_count'] = $this->getCountBySelect($select, $this->itemModel->getTable());
        }

        if ($this->filterComposite->filter('name')) {
            $result['name'] = $this->itemModel->getLanguageName($object['id'], 'xx');
        }

        if ($this->filterComposite->filter('name_default')) {
            $name = $this->itemModel->getLanguageName($object['id'], 'xx');
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
            $rows = $this->itemModel->getRows([
                'item_type_id' => Item::BRAND,
                'descendant'   => $object['id']
            ]);

            $result['brands'] = $this->extractValue('brands', $rows);
        }

        if ($this->filterComposite->filter('categories')) {
            $rows = $this->itemModel->getRows([
                'item_type_id' => Item::CATEGORY,
                'child'        => [
                    'item_type_id' => [Item::VEHICLE, Item::ENGINE],
                    'descendant'   => $object['id']
                ]
            ]);

            $result['categories'] = $this->extractValue('categories', $rows);
        }

        if ($this->filterComposite->filter('twins_groups')) {
            $rows = $this->itemModel->getRows([
                'item_type_id' => Item::TWINS,
                'descendant'   => $object['id']
            ]);

            $result['twins_groups'] = $this->extractValue('twins_groups', $rows);
        }

        if ($this->filterComposite->filter('url')) {
            $url = null;
            switch ($object['item_type_id']) {
                case Item::CATEGORY:
                    $url = $this->router->assemble([
                        'action'           => 'category',
                        'category_catname' => $object['catname'],
                    ], [
                        'name' => 'categories'
                    ]);
                    break;
                case Item::TWINS:
                    $url = $this->router->assemble([
                        'id' => $object['id'],
                    ], [
                        'name' => 'twins/group'
                    ]);
                    break;

                case Item::ENGINE:
                case Item::VEHICLE:
                    $url = $listBuilder->getDetailsUrl($object);
                    break;
            }

            $result['url'] = $url;
        }

        if ($this->filterComposite->filter('produced')) {
            $value = (int)$object['produced'];
            $result['produced'] = $value > 0 ? $value : null;
            $result['produced_exactly'] = (bool)$object['produced_exactly'];
        }

        if ($this->filterComposite->filter('design')) {
            $designRow = $this->itemModel->getRow([
                'language' => $this->language,
                'columns'  => ['catname', 'name'],
                'child'    => [
                    'link_type' => ItemParent::TYPE_DESIGN,
                    'columns'   => [
                        'brand_item_catname' => 'link_catname'
                    ],
                    'descendant_or_self' => $object['id']
                ]
            ]);

            if ($designRow) {
                $result['design'] = [
                    'brand_name' => $designRow['name'], //TODO: formatter
                    'url'        => $this->router->assemble([
                        'action'        => 'brand-item',
                        'brand_catname' => $designRow['catname'] ? $designRow['catname'] : 'test',
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
            if ($object['item_type_id'] == Item::ENGINE) {
                $result['engine_vehicles'] = $this->getVehiclesOnEngine($object);
            }
        }

        if ($this->filterComposite->filter('public_urls')) {
            $result['public_urls'] = $this->getItemPublicUrls($object);
        }

        if ($this->filterComposite->filter('logo')) {
            $result['logo'] = $this->extractValue('logo', [
                'image' => $object['logo_id']
            ]);
        }

        if ($this->filterComposite->filter('brandicon')) {
            $result['brandicon'] = $this->extractValue('brandicon', [
                'image'  => $object['logo_id'],
                'format' => 'brandicon2'
            ]);
        }

        if ($this->filterComposite->filter('has_text')) {
            $result['has_text'] = $this->itemModel->hasFullText($object['id']);
        }

        if ($this->filterComposite->filter('description')) {
            $result['description'] = $this->itemModel->getTextOfItem($object['id'], $this->language);
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
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
            $this->userRole = $this->userModel->getUserRole($this->userId);
        }

        return $this->userRole;
    }

    private function isAllowed(string $resource, string $privilege): bool
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
            $rows = $this->itemModel->getRows([
                'id'    => $ids,
                'order' => $this->catalogue->itemOrdering()
            ]);
            foreach ($rows as $row) {
                $cataloguePaths = $this->catalogue->getCataloguePaths($row['id']);
                foreach ($cataloguePaths as $cPath) {
                    $result[] = [
                        'name_html' => $this->itemNameFormatter->formatHtml(
                            $this->itemModel->getNameData($row, $this->language),
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

    private function getItemPublicUrls($item)
    {
        if ($item['item_type_id'] == Item::FACTORY) {
            return [
                $this->router->assemble([
                    'action' => 'factory',
                    'id'     => $item['id'],
                ], [
                    'name' => 'factories/factory'
                ])
            ];
        }

        if ($item['item_type_id'] == Item::CATEGORY) {
            return [
                $this->router->assemble([
                    'action'           => 'category',
                    'category_catname' => $item['catname'],
                ], [
                    'name' => 'categories'
                ])
            ];
        }

        if ($item['item_type_id'] == Item::TWINS) {
            return [
                $this->router->assemble([
                    'id' => $item['id'],
                ], [
                    'name' => 'twins/group'
                ])
            ];
        }

        if ($item['item_type_id'] == Item::BRAND) {
            return [
                $this->router->assemble([
                    'brand_catname' => $item['catname'],
                ], [
                    'name' => 'catalogue'
                ])
            ];
        }

        return $this->walkUpUntilBrand((int)$item['id'], []);
    }

    private function walkUpUntilBrand(int $id, array $path)
    {
        $urls = [];

        $parentRows = $this->itemParent->getParentRows($id);

        foreach ($parentRows as $parentRow) {
            $brand = $this->itemModel->getRow([
                'item_type_id' => Item::BRAND,
                'id'           => $parentRow['parent_id']
            ]);

            if ($brand) {
                $urls[] = $this->router->assemble([
                    'action'        => 'brand-item',
                    'brand_catname' => $brand['catname'],
                    'car_catname'   => $parentRow['catname'],
                    'path'          => $path
                ], [
                    'name' => 'catalogue'
                ]);
            }

            $urls = array_merge(
                $urls,
                $this->walkUpUntilBrand((int)$parentRow['parent_id'], array_merge([$parentRow['catname']], $path))
            );
        }

        return $urls;
    }
}
