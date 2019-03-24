<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\User;

use Application\Hydrator\Api\ItemHydrator;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\Brand;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Categories;
use Application\Model\Item;
use Application\Model\Picture;
use Application\Model\PictureItem;
use Application\Model\Twins;
use Application\Service\SpecificationsService;

class IndexController extends AbstractRestfulController
{
    private $cache;

    /**
     * @var Brand
     */
    private $brand;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Categories
     */
    private $categories;

    /**
     * @var Twins
     */
    private $twins;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var ItemHydrator
     */
    private $itemHydrator;

    /**
     * @var RestHydrator
     */
    private $userHydrator;

    /**
     * @var CarOfDay
     */
    private $itemOfDay;

    /**
     * @var Catalogue
     */
    private $catalogue;

    private $router;

    public function __construct(
        $cache,
        Brand $brand,
        Item $item,
        Categories $categories,
        Twins $twins,
        SpecificationsService $specsService,
        User $userModel,
        CarOfDay $itemOfDay,
        Catalogue $catalogue,
        ItemHydrator $itemHydrator,
        RestHydrator $userHydrator,
        $router
    ) {
        $this->cache = $cache;
        $this->brand = $brand;
        $this->item = $item;
        $this->categories = $categories;
        $this->twins = $twins;
        $this->specsService = $specsService;
        $this->userModel = $userModel;
        $this->itemOfDay = $itemOfDay;
        $this->catalogue = $catalogue;
        $this->itemHydrator = $itemHydrator;
        $this->userHydrator = $userHydrator;
        $this->router = $router;
    }

    public function brandsAction()
    {
        $language = $this->language();

        $cacheKey = 'API_INDEX_BRANDS_' . $language;
        $success = false;
        $brands = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            // cache missing

            $items = $this->brand->getTopBrandsList($language);
            foreach ($items as &$item) {
                $item['url'] = $this->url()->fromRoute('catalogue', [
                    'action'        => 'brand',
                    'brand_catname' => $item['catname'],
                ]);
                $item['new_cars_url'] = $this->url()->fromRoute('brands/newcars', [
                    'brand_id' => $item['id']
                ]);
            }
            unset($item);

            $brands = [
                'brands' => $items,
                'total'  => $this->item->getCount([
                    'item_type_id' => Item::BRAND
                ])
            ];

            $this->cache->setItem($cacheKey, $brands);
        }

        return new JsonModel($brands);
    }

    public function personsContentAction()
    {
        $language = $this->language();

        $cacheKey = 'API_INDEX_PERSONS_CONTENT_' . $language;
        $success = false;
        $contentPersons = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $contentPersons = $this->item->getRows([
                'language'     => $language,
                'columns'      => ['id', 'name'],
                'limit'        => 5,
                'item_type_id' => 8,
                'pictures'     => [
                    'status' => Picture::STATUS_ACCEPTED,
                    'type'   => PictureItem::PICTURE_CONTENT
                ],
                'order'        => new Sql\Expression('COUNT(pi1.picture_id) desc')
            ]);

            $this->cache->setItem($cacheKey, $contentPersons);
        }

        return new JsonModel([
            'items' => $contentPersons
        ]);
    }

    public function personsAuthorAction()
    {
        $language = $this->language();

        $cacheKey = 'API_INDEX_PERSONS_AUTHOR_' . $language;
        $success = false;
        $authorPersons = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $authorPersons = $this->item->getRows([
                'language'     => $language,
                'columns'      => ['id', 'name'],
                'limit'        => 5,
                'item_type_id' => 8,
                'pictures'     => [
                    'status' => Picture::STATUS_ACCEPTED,
                    'type'   => PictureItem::PICTURE_AUTHOR
                ],
                'order'        => new Sql\Expression('COUNT(pi1.picture_id) desc')
            ]);

            $this->cache->setItem($cacheKey, $authorPersons);
        }

        return new JsonModel([
            'items' => $authorPersons
        ]);
    }

    public function categoriesAction()
    {
        $language = $this->language();

        // categories
        $cacheKey = 'API_INDEX_CATEGORY_' . $language;
        $success = false;
        $categories = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $categories = $this->categories->getCategoriesList(null, $language, 15, 'name');

            foreach ($categories as &$category) {
                $category['new_cars_url'] = $this->url()->fromRoute('category-newcars', [
                    'item_id' => $category['id']
                ]);
            }
            unset($category);

            $this->cache->setItem($cacheKey, $categories);
        }

        return new JsonModel([
            'items' => $categories
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function factoriesAction()
    {
        $cacheKey = 'API_INDEX_FACTORIES_2';
        $success = true;
        $factories = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $select = new Sql\Select($this->item->getTable()->getTable());
            $select
                ->columns([
                    'id',
                    'name',
                    'count'     => new Sql\Expression('COUNT(1)'),
                    'new_count' => new Sql\Expression(
                        'COUNT(IF(item_parent.timestamp > DATE_SUB(NOW(), INTERVAL ? DAY), 1, NULL))',
                        7
                    )
                ])
                ->where([
                    'item.item_type_id = ?' => Item::FACTORY,
                    new Sql\Predicate\In('product.item_type_id', [
                        Item::VEHICLE,
                        Item::ENGINE
                    ])
                ])
                ->join('item_parent', 'item.id = item_parent.parent_id', [])
                ->join(['product' => 'item'], 'item_parent.item_id = product.id', [])
                ->group('item.id')
                ->order(['new_count desc', 'count desc'])
                ->limit(8);

            $items = $this->item->getTable()->selectWith($select);

            $factories = [];
            foreach ($items as $item) {
                $factories[] = [
                    'id'        => $item['id'],
                    'name'      => $item['name'],
                    'count'     => $item['count'],
                    'new_count' => $item['new_count'],
                    'new_url'   => $this->url()->fromRoute('factories/newcars', [
                        'item_id' => $item['id']
                    ])
                ];
            }

            $this->cache->setItem($cacheKey, $factories);
        }

        return new JsonModel([
            'items' => $factories
        ]);
    }

    public function twinsAction()
    {
        $language = $this->language();

        $cacheKey = 'API_INDEX_INTERESTS_TWINS_BLOCK_' . $language;
        $success = false;
        $twinsBlock = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $twinsBrands = $this->twins->getBrands([
                'language' => $language,
                'limit'    => 20
            ]);

            $twinsBlock = [
                'brands'       => $twinsBrands,
                'brands_count' => $this->twins->getTotalBrandsCount()
            ];

            $this->cache->setItem($cacheKey, $twinsBlock);
        }

        return new JsonModel($twinsBlock);
    }

    public function specItemsAction()
    {
        $language = $this->language();

        $cacheKey = 'API_INDEX_SPEC_CARS_3_' . $language;
        $success = false;
        $cars = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $select = $this->item->getSelect([
                'limit' => 4
            ]);
            $select
                ->join('attrs_user_values', 'item.id = attrs_user_values.item_id', [])
                ->where(['update_date > DATE_SUB(NOW(), INTERVAL 3 DAY)'])
                ->having(['count(attrs_user_values.attribute_id) > 10'])
                ->group('item.id')
                ->order(new Sql\Expression('MAX(attrs_user_values.update_date) DESC'));

            $cars = [];
            foreach ($this->item->getTable()->selectWith($select) as $row) {
                $cars[] = $row;
            }

            $this->cache->setItem($cacheKey, $cars);
        }

        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $user = $this->user()->get();

        $this->itemHydrator->setOptions([
            'language' => $language,
            'fields'   => [
                'name_html' => true,
                'name_default' => true,
                'description' => true,
                'has_text' => true,
                'produced' => true,
                'design' => true,
                'engine_vehicles' => true,
                'url' => true,
                'can_edit_specs' => true,
                'specs_url' => true,
                'more_pictures_url' => true,
                'categories' => [
                    'catname'   => true,
                    'name_html' => true
                ],
                'twins_groups' => true,
                'preview_pictures' => [
                    'picture' => ['thumb_medium' => true],
                    'url' => true
                ],
                'childs_count' => true,
                'total_pictures' => true
            ],
            'user_id'  => $user ? $user['id'] : null,
            'preview_pictures' => [
                'perspective_page_id' => 1
            ]
        ]);

        $this->userHydrator->setOptions([
            'language' => $language,
            'fields'   => [],
            'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($cars as $row) {
            $extracted = $this->itemHydrator->extract($row);
            $extracted['contributors'] = [];
            $contribPairs = $this->specsService->getContributors([$row['id']]);
            if ($contribPairs) {
                $contributors = $this->userModel->getRows([
                    'id' => array_keys($contribPairs),
                    'not_deleted'
                ]);
                foreach ($contributors as $contributor) {
                    $extracted['contributors'][] = $this->userHydrator->extract($contributor);
                }

            }
            $items[] = $extracted;
        }

        return new JsonModel([
            'items' => $items
        ]);
    }

    public function itemOfDayAction()
    {
        $language = $this->language();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $httpsFlag = $this->getRequest()->getUri()->getScheme();

        $itemOfDay = $this->itemOfDay->getCurrent();

        $itemOfDayInfo = null;

        if ($itemOfDay) {
            $key = 'API_ITEM_OF_DAY_119_' . $itemOfDay['item_id'] . '_' . $language . '_' . $httpsFlag;

            $success = false;
            $itemOfDayInfo = $this->cache->getItem($key, $success);
            if (! $success) {
                $item = $this->item->getRow([
                    'id'       => $itemOfDay['item_id'],
                    'language' => $language
                ]);

                if ($item) {
                    /* @phan-suppress-next-line PhanUndeclaredMethod */
                    $user = $this->user()->get();

                    $this->itemHydrator->setOptions([
                        'language' => $language,
                        'fields'   => [
                            'name_html'               => true,
                            'item_of_day_pictures'    => true,
                            'accepted_pictures_count' => true,
                            'twins_groups'            => true,
                            'categories'              => [
                                'name_html' => true,
                                'catname'   => true
                            ],
                        ],
                        'user_id'  => $user ? $user['id'] : null,
                    ]);

                    $item = $this->itemHydrator->extract($item);

                    if ($item['accepted_pictures_count'] > 6 && $item['item_type_id'] != Item::CATEGORY) {
                        $cataloguePaths = $this->catalogue->getCataloguePaths($item['id'], [
                            'breakOnFirst' => true
                        ]);

                        foreach ($cataloguePaths as $path) {
                            $url = $this->router->assemble([
                                'action'        => 'brand-item-pictures',
                                'brand_catname' => $path['brand_catname'],
                                'car_catname'   => $path['car_catname'],
                                'path'          => $path['path']
                            ], [
                                'name' => 'catalogue'
                            ]);
                            $item['public_url'] = $url;
                            break;
                        }
                    }
                }

                $itemOfDayUser = null;
                if ($itemOfDay['user_id']) {
                    $itemOfDayUser = $this->userModel->getRow([
                        'id' => $itemOfDay['user_id']
                    ]);
                }


                $itemOfDayInfo = [
                    'item' => $item,
                    'user' => $itemOfDayUser
                ];

                $this->cache->setItem($key, $itemOfDayInfo);
            }
        }

        return new JsonModel($itemOfDayInfo);
    }
}
