<?php

namespace Application\Controller;

use Zend\Cache\Storage\StorageInterface;
use Zend\Db\Sql;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\User;

use Application\Controller\Plugin\Pic;
use Application\Model\CarOfDay;
use Application\Model\Categories;
use Application\Model\Brand;
use Application\Model\Item;
use Application\Model\Perspective;
use Application\Model\Picture;
use Application\Model\Twins;
use Application\Service\SpecificationsService;
use Application\Model\PictureItem;

/**
 * Class IndexController
 * @package Application\Controller
 *
 * @method Pic pic()
 * @method string language()
 */
class IndexController extends AbstractActionController
{
    /**
     * @var StorageInterface
     */
    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var CarOfDay
     */
    private $itemOfDay;

    /**
     * @var Item
     */
    private $item;

    /**
     * @var Perspective
     */
    private $perspective;

    /**
     * @var Twins
     */
    private $twins;

    /**
     * @var Picture
     */
    private $picture;

    /**
     * @var Brand
     */
    private $brand;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var Categories
     */
    private $categories;

    public function __construct(
        StorageInterface $cache,
        SpecificationsService $specsService,
        CarOfDay $itemOfDay,
        Categories $categories,
        Perspective $perspective,
        Twins $twins,
        Picture $picture,
        Item $item,
        Brand $brand,
        User $userModel
    ) {
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->itemOfDay = $itemOfDay;
        $this->categories = $categories;
        $this->perspective = $perspective;
        $this->twins = $twins;
        $this->picture = $picture;

        $this->item = $item;
        $this->brand = $brand;
        $this->userModel = $userModel;
    }

    private function brands()
    {
        $language = $this->language();

        $cacheKey = 'INDEX_BRANDS_HTML266' . $language;
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
                'brands'      => $items,
                'totalBrands' => $this->item->getCount([
                    'item_type_id' => Item::BRAND
                ])
            ];

            $this->cache->setItem($cacheKey, $brands);
        }

        return $brands;
    }

    private function itemOfDay()
    {
        $language = $this->language();
        /* @phan-suppress-next-line PhanUndeclaredMethod */
        $httpsFlag = $this->getRequest()->getUri()->getScheme();

        $itemOfDay = $this->itemOfDay->getCurrent();

        $itemOfDayInfo = null;

        if ($itemOfDay) {
            $key = 'ITEM_OF_DAY_117_' . $itemOfDay['item_id'] . '_' . $language . '_' . $httpsFlag;

            $success = false;
            $itemOfDayInfo = $this->cache->getItem($key, $success);
            if (! $success) {
                $itemOfDayInfo = $this->itemOfDay->getItemOfDay($itemOfDay['item_id'], $itemOfDay['user_id'], $language);

                $this->cache->setItem($key, $itemOfDayInfo);
            }
        }

        return $itemOfDayInfo;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    private function factories()
    {
        $cacheKey = 'INDEX_FACTORIES_5';
        $success = false;
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
                    'url'       => '/ng/factories/' . $item['id'],
                    'new_url'   => $this->url()->fromRoute('factories/newcars', [
                        'item_id' => $item['id']
                    ])
                ];
            }

            $this->cache->setItem($cacheKey, $factories);
        }

        return $factories;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function indexAction()
    {
        $language = $this->language();

        $rows = $this->picture->getRows([
            'status'           => Picture::STATUS_ACCEPTED,
            'order'            => 'accept_datetime_desc',
            'accepted_in_days' => 3,
            'limit'            => 6
        ]);

        $newPicturesData = $this->pic()->listData($rows, [
            'width' => 6
        ]);

        // categories
        $cacheKey = 'INDEX_CATEGORY13_' . $language;
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

        // БЛИЗНЕЦЫ
        $cacheKey = 'INDEX_INTERESTS_TWINS_BLOCK_30_' . $language;
        $twinsBlock = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $twinsBrands = $this->twins->getBrands([
                'language' => $language,
                'limit'    => 20
            ]);

            foreach ($twinsBrands as &$brand) {
                $brand['url'] = '/ng/twins/' . $brand['catname'];
            }
            unset($brand);

            $twinsBlock = [
                'brands'       => $twinsBrands,
                'brands_count' => $this->twins->getTotalBrandsCount()
            ];

            $this->cache->setItem($cacheKey, $twinsBlock);
        }

        $cacheKey = 'INDEX_SPEC_CARS_16_' . $language;
        $cars = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $select = new Sql\Select($this->item->getTable()->getTable());
            $select
                ->join('attrs_user_values', 'item.id = attrs_user_values.item_id', [])
                ->where(['update_date > DATE_SUB(NOW(), INTERVAL 3 DAY)'])
                ->having(['count(attrs_user_values.attribute_id) > 10'])
                ->group('item.id')
                ->order(new Sql\Expression('MAX(attrs_user_values.update_date) DESC'))
                ->limit(4);

            $cars = [];
            foreach ($this->item->getTable()->selectWith($select) as $row) {
                $cars[] = $row;
            }

            $this->cache->setItem($cacheKey, $cars);
        }

        $specsCars = $this->car()->listData($cars, [
            'pictureFetcher' => new Item\PerspectivePictureFetcher([
                'pictureModel'         => $this->picture,
                'itemModel'            => $this->item,
                'perspective'          => $this->perspective,
                'type'                 => null,
                'onlyExactlyPictures'  => false,
                'dateSort'             => false,
                'disableLargePictures' => true,
                'perspectivePageId'    => 1,
                'onlyChilds'           => []
            ]),
            'listBuilder' => new Item\ListBuilder([
                'catalogue'    => $this->catalogue(),
                'router'       => $this->getEvent()->getRouter(),
                'picHelper'    => $this->getPluginManager()->get('pic'),
                'specsService' => $this->specsService
            ]),
            'disableDescription'   => true,
            'callback'             =>
                /**
                 * @suppress PhanPluginMixedKeyNoKey
                 */
                function (&$item) {
                    $contribPairs = $this->specsService->getContributors([$item['id']]);
                    if ($contribPairs) {
                        $item['contributors'] = $this->userModel->getRows([
                            'id' => array_keys($contribPairs),
                            'not_deleted'
                        ]);
                    } else {
                        $item['contributors'] = [];
                    }
                }
        ]);

        $cacheKey = 'INDEX_PERSONS_CONTENT_' . $language;
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

        $cacheKey = 'INDEX_PERSONS_AUTHOR_' . $language;
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

        return [
            'brands'      => $this->brands(),
            'factories'   => $this->factories(),
            'twinsBlock'  => $twinsBlock,
            'categories'  => $categories,
            'newPictures' => $newPicturesData,
            'itemOfDay'   => $this->itemOfDay(),
            'specsCars'   => $specsCars,
            'mosts'       => [
                '/mosts/fastest/roadster'          => 'mosts/fastest/roadster',
                '/mosts/mighty/sedan/today'        => 'mosts/mighty/sedan/today',
                '/mosts/dynamic/universal/2000-09' => 'mosts/dynamic/universal/2000-09',
                '/mosts/heavy/truck'               => 'mosts/heavy/truck'
            ],
            'contentPersons' => $contentPersons,
            'authorPersons'  => $authorPersons
        ];
    }

    public function ngAction()
    {
        $path = $this->params('path');

        if ($path) {
            /* @phan-suppress-next-line PhanUndeclaredMethod */
            $uri = $this->getRequest()->getUri();

            $query = $uri->getQuery();

            return $this->redirect()->toRoute('ng', [
                'path' => ''
            ], [
                'fragment' => '!/'.$path . ($query ? '?'.$query : '')
            ], false);
        }
    }
}
