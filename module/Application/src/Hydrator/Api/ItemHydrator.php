<?php

namespace Application\Hydrator\Api;

use Application\Comments;
use Application\ItemNameFormatter;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Perspective;
use Application\Model\PerspectivePictureFetcher;
use Application\Model\Picture;
use Application\Model\UserItemSubscribe;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;
use ArrayAccess;
use Autowp\Comments\Attention;
use Autowp\Image\StorageInterface;
use Autowp\TextStorage;
use Autowp\User\Model\User;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Hydrator\Exception\InvalidArgumentException;
use Laminas\Permissions\Acl\Acl;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\ArrayUtils;
use Traversable;

use function array_keys;
use function array_merge;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function strcmp;

class ItemHydrator extends AbstractRestHydrator
{
    private int $userId = 0;

    private ?string $userRole;

    private ItemNameFormatter $itemNameFormatter;

    private TableGateway $specTable;

    private Catalogue $catalogue;

    private SpecificationsService $specsService;

    private Item $itemModel;

    private TextStorage\Service $textStorage;

    private Picture $picture;

    private TableGateway $linkTable;

    private SpecificationsService $specificationsService;

    private UserItemSubscribe $userItemSubscribe;

    private Perspective $perspective;

    private ItemParent $itemParent;

    private User $userModel;

    private CarOfDay $carOfDay;

    private StorageInterface $imageStorage;

    private Acl $acl;

    private VehicleType $vehicleType;

    private array $previewPictures = [];

    private Comments $comments;

    private int $mostsMinCarsCount = 1;

    private int $routeBrandId = 0;

    private array $cataloguePaths = [];

    public function __construct(ServiceLocatorInterface $serviceManager)
    {
        parent::__construct();

        $tables          = $serviceManager->get('TableManager');
        $this->picture   = $serviceManager->get(Picture::class);
        $this->linkTable = $tables->get('links');
        $this->specTable = $tables->get('spec');

        $this->comments = $serviceManager->get(Comments::class);

        $this->userModel = $serviceManager->get(User::class);

        $this->perspective       = $serviceManager->get(Perspective::class);
        $this->userItemSubscribe = $serviceManager->get(UserItemSubscribe::class);

        $this->specificationsService = $serviceManager->get(SpecificationsService::class);

        $this->itemNameFormatter = $serviceManager->get(ItemNameFormatter::class);

        $this->itemModel  = $serviceManager->get(Item::class);
        $this->itemParent = $serviceManager->get(ItemParent::class);

        $this->acl         = $serviceManager->get(Acl::class);
        $this->textStorage = $serviceManager->get(TextStorage\Service::class);

        $this->catalogue = $serviceManager->get(Catalogue::class);

        $this->specsService = $serviceManager->get(SpecificationsService::class);

        $this->carOfDay = $serviceManager->get(CarOfDay::class);

        $this->imageStorage = $serviceManager->get(StorageInterface::class);

        $this->vehicleType = $serviceManager->get(VehicleType::class);

        $config                  = $serviceManager->get('Config');
        $this->mostsMinCarsCount = $config['mosts_min_vehicles_count'];

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('brands', $strategy);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('categories', $strategy);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('childs', $strategy);

        $strategy = new Strategy\Items($serviceManager);
        $this->addStrategy('twins_groups', $strategy);

        $strategy = new Strategy\PreviewPictures($serviceManager);
        $this->addStrategy('preview_pictures', $strategy);

        $strategy = new Strategy\Picture($serviceManager);
        $this->addStrategy('front_picture', $strategy);

        $strategy = new Strategy\Picture($serviceManager);
        $this->addStrategy('exact_picture', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('logo', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('logo120', $strategy);

        $strategy = new Strategy\Image($serviceManager);
        $this->addStrategy('brandicon', $strategy);
    }

    /**
     * @param  array|Traversable $options
     * @throws InvalidArgumentException
     */
    public function setOptions($options): self
    {
        parent::setOptions($options);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (! is_array($options)) {
            throw new InvalidArgumentException(
                'The options parameter must be an array or a Traversable'
            );
        }

        if (isset($options['user_id'])) {
            $this->setUserId($options['user_id']);
        }

        if (isset($options['preview_pictures'])) {
            $this->setPreviewPictures($options['preview_pictures']);
        }

        if (isset($options['route_brand_id'])) {
            $this->routeBrandId = (int) $options['route_brand_id'];
        }

        return $this;
    }

    public function setPreviewPictures(array $options): self
    {
        $this->previewPictures = $options;

        return $this;
    }

    /**
     * @param int|null $userId
     */
    public function setUserId($userId = null): self
    {
        $this->userId = $userId;

        //$this->getStrategy('content')->setUser($user);
        //$this->getStrategy('replies')->setUser($user);

        return $this;
    }

    /**
     * @param array|ArrayAccess $object
     * @throws Exception
     */
    private function getNameData($object, string $language = 'en'): array
    {
        if (! is_string($language)) {
            throw new Exception('`language` is not string');
        }

        $name = $this->itemModel->getName($object['id'], $language);

        $spec     = null;
        $specFull = null;
        if ($object['spec_id']) {
            $specRow = $this->specTable->select(['id' => (int) $object['spec_id']])->current();
            if ($specRow) {
                $spec     = $specRow['short_name'];
                $specFull = $specRow['name'];
            }
        }

        return [
            'begin_model_year'          => $object['begin_model_year'],
            'end_model_year'            => $object['end_model_year'],
            'begin_model_year_fraction' => $object['begin_model_year_fraction'],
            'end_model_year_fraction'   => $object['end_model_year_fraction'],
            'spec'                      => $spec,
            'spec_full'                 => $specFull,
            'body'                      => $object['body'],
            'name'                      => $name,
            'begin_year'                => $object['begin_year'],
            'end_year'                  => $object['end_year'],
            'today'                     => $object['today'],
            'begin_month'               => $object['begin_month'],
            'end_month'                 => $object['end_month'],
        ];
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     */
    private function getCountBySelect(Sql\Select $select, TableGateway $table): int
    {
        $select->columns(['count' => new Sql\Expression('count(1)')]);
        $row = $table->selectWith($select)->current();
        return $row ? (int) $row['count'] : 0;
    }

    private function getCataloguePath(int $id, array $options): array
    {
        if (! isset($this->cataloguePaths[$id])) {
            $this->cataloguePaths[$id] = $this->catalogue->getCataloguePaths($id, $options);
        }

        return $this->cataloguePaths[$id];
    }

    /**
     * @return string[]|null
     */
    public function getDetailsRoute(int $itemId, array $options): ?array
    {
        $cataloguePaths = $this->getCataloguePath($itemId, $options);

        foreach ($cataloguePaths as $cPath) {
            return array_merge(['/', $cPath['brand_catname'], $cPath['car_catname']], $cPath['path']);
        }

        return null;
    }

    /**
     * @return string[]|null
     */
    public function getSpecificationsRoute(int $itemId): ?array
    {
        $hasSpecs = $this->specsService->hasSpecs($itemId);

        if (! $hasSpecs) {
            return null;
        }

        $cataloguePaths = $this->getCataloguePath($itemId, [
            'toBrand'      => true,
            'breakOnFirst' => true,
        ]);
        foreach ($cataloguePaths as $path) {
            return ['/', $path['brand_catname'], $path['car_catname'], ...$path['path'], 'specifications'];
        }

        return null;
    }

    /**
     * @param array|ArrayAccess $object
     * @throws Exception
     */
    public function extract($object): ?array
    {
        $nameData = $this->getNameData($object, $this->language);

        $isModer = false;
        $role    = $this->getUserRole();
        if ($role) {
            $isModer = $this->acl->inheritsRole($role, 'moder');
        }

        $result = [
            'id'           => (int) $object['id'],
            'item_type_id' => (int) $object['item_type_id'],
            'catname'      => $object['catname'],
            'is_group'     => (bool) $object['is_group'],
        ];

        if ($this->filterComposite->filter('alt_names')) {
            // alt names
            $altNames  = [];
            $altNames2 = [];

            $langNames = $this->itemModel->getNames($object['id']);

            $currentLangName = null;
            foreach ($langNames as $lang => $langName) {
                if ($lang === 'xx') {
                    continue;
                }
                $name = $langName;
                if (! isset($altNames[$name])) {
                    $altNames[$langName] = [];
                }
                $altNames[$name][] = $lang;

                if ($this->language === $lang) {
                    $currentLangName = $name;
                }
            }

            foreach ($altNames as $name => $codes) {
                if (strcmp($name, $currentLangName) !== 0) {
                    $altNames2[$name] = $codes;
                }
            }

            if ($currentLangName) {
                unset($altNames2[$currentLangName]);
            }

            $a = [];
            foreach ($altNames2 as $name => $languages) {
                $a[] = [
                    'languages' => $languages,
                    'name'      => $name,
                ];
            }

            $result['alt_names'] = $a;
        }

        if ($this->filterComposite->filter('childs_counts')) {
            $pairs = $this->itemParent->getChildItemLinkTypesCount($object['id']);

            $result['childs_counts'] = [
                'stock'  => $pairs[ItemParent::TYPE_DEFAULT] ?? 0,
                'tuning' => $pairs[ItemParent::TYPE_TUNING] ?? 0,
                'sport'  => $pairs[ItemParent::TYPE_SPORT] ?? 0,
            ];
        }

        if ($this->filterComposite->filter('mosts_active')) {
            $carsCount = $this->itemModel->getCount([
                'ancestor' => $object['id'],
            ]);

            $result['mosts_active'] = $carsCount >= $this->mostsMinCarsCount;
        }

        if ($this->filterComposite->filter('engine_id')) {
            $result['engine_id']      = $object['engine_item_id'] ? (int) $object['engine_item_id'] : null;
            $result['engine_inherit'] = (bool) $object['engine_inherit'];
        }

        if ($this->filterComposite->filter('specs_route')) {
            if (in_array($object['item_type_id'], [Item::CATEGORY, Item::ENGINE, Item::TWINS, Item::VEHICLE])) {
                $result['specs_route'] = $this->getSpecificationsRoute($object['id']);
            }
        }

        if ($this->filterComposite->filter('can_edit_specs')) {
            $isSpecsAvailabe          = in_array($object['item_type_id'], [Item::ENGINE, Item::TWINS, Item::VEHICLE]);
            $result['can_edit_specs'] = $isSpecsAvailabe && $this->isAllowed('specifications', 'edit');
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

        if ($this->filterComposite->filter('factories_of_brand_cars_count')) {
            $result['factories_of_brand_cars_count'] = (int) $object['factories_of_brand_cars_count'];
        }

        $textRequested = $this->filterComposite->filter('text');
        $descRequested = $this->filterComposite->filter('description');

        if ($textRequested || $descRequested) {
            $texts = $this->itemModel->getTextsOfItem($object['id'], $this->language);
            if ($descRequested) {
                $result['description'] = $texts['text'];
            }

            if ($textRequested) {
                $result['text'] = $texts['full_text'];
            }
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

        if ($this->filterComposite->filter('name_only')) {
            $result['name_only'] = $this->itemModel->getName($object['id'], $this->language);
        }

        if ($this->filterComposite->filter('other_names')) {
            $otherNames = [];
            foreach ($this->itemModel->getNames($object['id']) as $name) {
                if ($object['name'] !== $name) {
                    if (! in_array($name, $otherNames)) {
                        $otherNames[] = $name;
                    }
                }
            }

            $result['other_names'] = $otherNames;
        }

        if ($this->filterComposite->filter('current_pictures_count')) {
            $result['current_pictures_count'] = isset($object['current_pictures_count'])
                ? (int) $object['current_pictures_count']
                : null;
        }

        if ($this->filterComposite->filter('is_compiles_item_of_day')) {
            $result['is_compiles_item_of_day'] = $this->carOfDay->isComplies($object['id']);
        }

        if ($this->filterComposite->filter('childs_count')) {
            if (isset($object['childs_count'])) {
                $result['childs_count'] = (int) $object['childs_count'];
            } else {
                $result['childs_count'] = $this->itemParent->getChildItemsCount($object['id']);
            }
        }

        if ($this->filterComposite->filter('descendants_count')) {
            $result['descendants_count'] = $this->itemModel->getCount([
                'ancestor' => $object['id'],
            ]);
        }

        if ($this->filterComposite->filter('accepted_pictures_count')) {
            $result['accepted_pictures_count'] = $this->picture->getCount([
                'item'   => [
                    'ancestor_or_self' => $object['id'],
                ],
                'status' => Picture::STATUS_ACCEPTED,
            ]);
        }

        if ($this->filterComposite->filter('has_specs')) {
            $result['has_specs'] = $this->specificationsService->hasSpecs($object['id']);
        }

        if ($this->filterComposite->filter('has_child_specs')) {
            $result['has_child_specs'] = $this->specificationsService->hasChildSpecs($object['id']);
        }

        if ($this->filterComposite->filter('comments_topic_stat')) {
            $result['comments_topic_stat'] = $this->comments->service()->getTopicStat(
                Comments::ITEM_TYPE_ID,
                $object['id']
            );
        }

        if ($this->filterComposite->filter('front_picture')) {
            $picture                 = $this->picture->getRow([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => [
                    'ancestor_or_self' => (int) $object['id'],
                ],
                'order'  => 'front_angle',
            ]);
            $result['front_picture'] = $picture ? $this->extractValue('front_picture', $picture) : null;
        }

        if ($this->filterComposite->filter('exact_picture')) {
            $picture                 = $this->picture->getRow([
                'status' => Picture::STATUS_ACCEPTED,
                'item'   => (int) $object['id'],
                'order'  => 'likes',
            ]);
            $result['exact_picture'] = $picture ? $this->extractValue('exact_picture', $picture) : null;
        }

        $totalPictures       = null;
        $cFetcher            = null;
        $showTotalPictures   = $this->filterComposite->filter('total_pictures');
        $showPreviewPictures = $this->filterComposite->filter('preview_pictures');
        $onlyExactlyPictures = false;

        if ($showTotalPictures || $showPreviewPictures) {
            $pictureItemTypeId = null;
            if (isset($this->previewPictures['type_id']) && $this->previewPictures['type_id']) {
                $pictureItemTypeId = $this->previewPictures['type_id'];
            }

            $perspectivePageId = 0;
            if (isset($this->previewPictures['perspective_page_id']) && $this->previewPictures['perspective_page_id']) {
                $perspectivePageId = $this->previewPictures['perspective_page_id'];
            }

            $cFetcher = new PerspectivePictureFetcher([
                'pictureModel'        => $this->picture,
                'perspective'         => $this->perspective,
                'onlyExactlyPictures' => $onlyExactlyPictures,
                'perspectivePageId'   => $perspectivePageId,
                'pictureItemTypeId'   => $pictureItemTypeId,
            ]);

            $totalPictures = $cFetcher->getTotalPictures($object['id'], $onlyExactlyPictures);
        }

        if ($showPreviewPictures) {
            $pictures = $cFetcher->fetch($object, [
                'totalPictures' => $totalPictures,
            ]);

            $extractRoute = isset($this->fields['preview_pictures']['route']);

            if ($extractRoute) {
                foreach ($pictures['pictures'] as &$picture) {
                    if ($picture) {
                        $picture['route'] = ['/picture', $picture['row']['identity']];
                    }
                }
                unset($picture);
            }

            $result['preview_pictures'] = [
                'large_format' => $pictures['large_format'],
                'pictures'     => $this->extractValue('preview_pictures', $pictures['pictures'], [
                    'large_format' => $pictures['large_format'],
                ]),
            ];
        }

        if ($this->filterComposite->filter('brands')) {
            $rows = $this->itemModel->getRows([
                'item_type_id'       => Item::BRAND,
                'descendant_or_self' => $object['id'],
            ]);

            $result['brands'] = $this->extractValue('brands', $rows);
        }

        if ($this->filterComposite->filter('childs')) {
            $rows = $this->itemModel->getRows([
                'parent' => $object['id'],
            ]);

            $result['childs'] = $this->extractValue('childs', $rows);
        }

        if ($this->filterComposite->filter('item_of_day_pictures')) {
            $result['item_of_day_pictures'] = $this->carOfDay->getItemOfDayPictures($object['id'], $this->language);
        }

        if ($this->filterComposite->filter('descendant_twins_groups_count')) {
            $count = $this->itemModel->getCount([
                'item_type_id'       => Item::TWINS,
                'descendant_or_self' => [
                    'ancestor_or_self' => $object['id'],
                ],
            ]);

            $result['descendant_twins_groups_count'] = $this->extractValue('descendant_twins_groups_count', $count);
        }

        if ($this->filterComposite->filter('twins_groups')) {
            $rows = $this->itemModel->getRows([
                'item_type_id' => Item::TWINS,
                'descendant'   => $object['id'],
            ]);

            $result['twins_groups'] = $this->extractValue('twins_groups', $rows);
        }

        if ($this->filterComposite->filter('categories')) {
            $rows = $this->itemModel->getRows([
                'language'     => $this->language,
                'item_type_id' => Item::CATEGORY,
                'child'        => [
                    'item_type_id'       => [Item::VEHICLE, Item::ENGINE],
                    'descendant_or_self' => $object['id'],
                ],
            ]);

            $result['categories'] = $this->extractValue('categories', $rows);
        }

        if ($this->filterComposite->filter('logo120')) {
            $result['logo120'] = $this->extractValue('logo120', [
                'image'  => $object['logo_id'],
                'format' => 'logo',
            ]);
        }

        if ($this->filterComposite->filter('related_group_pictures')) {
            $carPictures = [];
            $groups      = $this->itemModel->getRelatedCarGroups($object['id']);
            if (count($groups) > 0) {
                $cars = $this->itemModel->getRows([
                    'id'    => array_keys($groups),
                    'order' => $this->catalogue->itemOrdering(),
                ]);

                foreach ($cars as $car) {
                    $ancestor = count($groups[$car['id']]) > 1
                        ? $groups[$car['id']]
                        : $car['id'];

                    $pictureRow = $this->picture->getRow([
                        'status' => Picture::STATUS_ACCEPTED,
                        'item'   => [
                            'ancestor_or_self' => [
                                'id' => $ancestor,
                            ],
                        ],
                        'order'  => 'ancestor_stock_front_first',
                    ]);

                    $src = null;
                    if ($pictureRow) {
                        $imagesInfo = $this->imageStorage->getFormatedImage(
                            $pictureRow['image_id'],
                            'picture-thumb'
                        );
                        $src        = $imagesInfo->getSrc();
                    }

                    $cataloguePaths = $this->catalogue->getCataloguePaths($car['id'], [
                        'breakOnFirst' => true,
                    ]);

                    $route = null;
                    foreach ($cataloguePaths as $cataloguePath) {
                        $route = array_merge([
                            '/',
                            $cataloguePath['brand_catname'],
                            $cataloguePath['car_catname'],
                        ], $cataloguePath['path']);
                        break;
                    }

                    $carPictures[] = [
                        'name'  => $this->itemNameFormatter->format($car, $this->language),
                        'src'   => $src,
                        'route' => $route,
                    ];
                }
            }

            $result['related_group_pictures'] = $carPictures;
        }

        if ($this->filterComposite->filter('route')) {
            $route = null;
            switch ($object['item_type_id']) {
                case Item::CATEGORY:
                    $route = ['/category', $object['catname']];
                    break;
                case Item::TWINS:
                    $route = ['/twins/group/', (string) $object['id']];
                    break;

                case Item::BRAND:
                    $route = ['/', (string) $object['catname']];
                    break;

                case Item::ENGINE:
                case Item::VEHICLE:
                    $route = $this->getDetailsRoute($object['id'], [
                        'breakOnFirst' => true,
                        'toBrand'      => $this->routeBrandId,
                        'stockFirst'   => true,
                    ]);
                    break;
            }

            $result['route'] = $route;
        }

        if ($this->filterComposite->filter('produced')) {
            $value                      = (int) $object['produced'];
            $result['produced']         = $value > 0 ? $value : null;
            $result['produced_exactly'] = (bool) $object['produced_exactly'];
        }

        if ($this->filterComposite->filter('design')) {
            $result['design'] = $this->itemModel->getDesignInfo($object['id'], $this->language);
        }

        if ($this->filterComposite->filter('engine_vehicles')) {
            if ((int) $object['item_type_id'] === Item::ENGINE) {
                $result['engine_vehicles'] = $this->getVehiclesOnEngine($object);
            }
        }

        if ($this->filterComposite->filter('name_default')) {
            $name                   = $this->itemModel->getLanguageName($object['id'], 'xx');
            $result['name_default'] = $nameData['name'] === $name ? null : $name;
        }

        if ($isModer) {
            $result['body'] = (string) $object['body'];

            if ($this->filterComposite->filter('comments_attentions_count')) {
                $result['comments_attentions_count'] = $this->comments->service()->getTotalMessagesCount([
                    'attention' => Attention::REQUIRED,
                    'type'      => Comments::PICTURES_TYPE_ID,
                    'callback'  => function (Sql\Select $select) use ($object) {
                        $select
                            ->join('pictures', 'comment_message.item_id = pictures.id', [])
                            ->join('picture_item', 'pictures.id = picture_item.picture_id', [])
                            ->join('item_parent_cache', 'picture_item.item_id = item_parent_cache.item_id', [])
                            ->where(['item_parent_cache.parent_id' => $object['id']]);
                    },
                ]);
            }

            if ($this->filterComposite->filter('inbox_pictures_count')) {
                $result['inbox_pictures_count'] = $this->picture->getCount([
                    'item'   => [
                        'ancestor_or_self' => $object['id'],
                    ],
                    'status' => Picture::STATUS_INBOX,
                ]);
            }

            if ($this->filterComposite->filter('is_concept')) {
                if ($object['is_concept_inherit']) {
                    $result['is_concept'] = 'inherited';
                } else {
                    $result['is_concept'] = (bool) $object['is_concept'];
                }
            }

            if ($this->filterComposite->filter('spec_id')) {
                if ($object['spec_inherit']) {
                    $result['spec_id'] = 'inherited';
                } else {
                    $value             = (int) $object['spec_id'];
                    $result['spec_id'] = $value > 0 ? $value : null;
                }
            }

            if ($this->filterComposite->filter('begin_model_year')) {
                $value                               = (int) $object['begin_model_year'];
                $result['begin_model_year']          = $value > 0 ? $value : null;
                $value                               = $object['begin_model_year_fraction'];
                $result['begin_model_year_fraction'] = $value ? $value : null;
            }

            if ($this->filterComposite->filter('end_model_year')) {
                $value                             = (int) $object['end_model_year'];
                $result['end_model_year']          = $value > 0 ? $value : null;
                $value                             = $object['end_model_year_fraction'];
                $result['end_model_year_fraction'] = $value ? $value : null;
            }

            if ($this->filterComposite->filter('begin_year')) {
                $value                = (int) $object['begin_year'];
                $result['begin_year'] = $value > 0 ? $value : null;
            }

            if ($this->filterComposite->filter('begin_month')) {
                $value                 = (int) $object['begin_month'];
                $result['begin_month'] = $value > 0 ? $value : null;
            }

            if ($this->filterComposite->filter('end_year')) {
                $value              = (int) $object['end_year'];
                $result['end_year'] = $value > 0 ? $value : null;
            }

            if ($this->filterComposite->filter('end_month')) {
                $value               = (int) $object['end_month'];
                $result['end_month'] = $value > 0 ? $value : null;
            }

            if ($this->filterComposite->filter('today')) {
                $result['today'] = $object['today'] === null ? null : (bool) $object['today'];
            }

            if ($this->filterComposite->filter('subscription') && $this->userId) {
                $result['subscription'] = $this->userItemSubscribe->isSubscribed(
                    (int) $this->userId,
                    (int) $object['id']
                );
            }

            if ($this->filterComposite->filter('full_name')) {
                $result['full_name'] = $object['full_name'];
            }

            if ($showTotalPictures) {
                $result['total_pictures'] = $totalPictures;
            }

            if ($this->filterComposite->filter('pictures_count')) {
                $result['pictures_count'] = $this->picture->getCount([
                    'item' => $object['id'],
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

            if ($this->filterComposite->filter('public_routes')) {
                $result['public_routes'] = $this->getItemPublicRoutes($object);
            }

            if ($this->filterComposite->filter('logo')) {
                $result['logo'] = $this->extractValue('logo', [
                    'image' => $object['logo_id'],
                ]);
            }

            if ($this->filterComposite->filter('brandicon')) {
                $result['brandicon'] = $this->extractValue('brandicon', [
                    'image'  => $object['logo_id'],
                    'format' => 'brandicon2',
                ]);
            }

            if ($this->filterComposite->filter('has_text')) {
                $result['has_text'] = $this->itemModel->hasFullText($object['id']);
            }

            if ($this->filterComposite->filter('attr_zone_id')) {
                $vehicleTypeIds         = $this->vehicleType->getVehicleTypes($object['id']);
                $result['attr_zone_id'] = $this->specificationsService->getZoneIdByCarTypeId(
                    $object['item_type_id'],
                    $vehicleTypeIds
                );
            }
        }

        return $result;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param object $object
     * @throws Exception
     */
    public function hydrate(array $data, $object)
    {
        throw new Exception("Not supported");
    }

    private function getUserRole(): ?string
    {
        if (! $this->userId) {
            return null;
        }

        if (! isset($this->userRole)) {
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

    /**
     * @param array|ArrayAccess $engine
     * @throws Exception
     */
    private function getVehiclesOnEngine($engine): array
    {
        $result = [];

        $ids = $this->itemModel->getEngineVehiclesGroups($engine['id'], [
            'groupJoinLimit' => 3,
        ]);

        if ($ids) {
            $rows = $this->itemModel->getRows([
                'id'    => $ids,
                'order' => $this->catalogue->itemOrdering(),
            ]);
            foreach ($rows as $row) {
                $cataloguePaths = $this->catalogue->getCataloguePaths($row['id']);
                foreach ($cataloguePaths as $cPath) {
                    $result[] = [
                        'name_html' => $this->itemNameFormatter->formatHtml(
                            $this->itemModel->getNameData($row, $this->language),
                            $this->language
                        ),
                        'route'     => ['/', $cPath['brand_catname'], $cPath['car_catname'], ...$cPath['path']],
                    ];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param array|ArrayAccess $item
     * @throws Exception
     */
    private function getItemPublicRoutes($item): array
    {
        if ((int) $item['item_type_id'] === Item::FACTORY) {
            return [
                ['/factories', (string) $item['id']],
            ];
        }

        if ((int) $item['item_type_id'] === Item::CATEGORY) {
            return [
                ['/category', $item['catname']],
            ];
        }

        if ((int) $item['item_type_id'] === Item::TWINS) {
            return [
                ['/twins', 'group', $item['id']],
            ];
        }

        if ((int) $item['item_type_id'] === Item::BRAND) {
            return [
                ['/' . $item['catname']],
            ];
        }

        return $this->walkUpUntilBrand((int) $item['id'], []);
    }

    /**
     * @throws Exception
     */
    private function walkUpUntilBrand(int $id, array $path): array
    {
        $routes = [];

        $parentRows = $this->itemParent->getParentRows($id);

        foreach ($parentRows as $parentRow) {
            $brand = $this->itemModel->getRow([
                'item_type_id' => Item::BRAND,
                'id'           => $parentRow['parent_id'],
            ]);

            if ($brand) {
                $routes[] = array_merge(['/', $brand['catname'], $parentRow['catname']], $path);
            }

            $routes = array_merge(
                $routes,
                $this->walkUpUntilBrand((int) $parentRow['parent_id'], array_merge([$parentRow['catname']], $path))
            );
        }

        return $routes;
    }
}
