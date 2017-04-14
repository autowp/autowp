<?php

namespace Application\Controller;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Autowp\User\Model\DbTable\User;

use Application\Model\CarOfDay;
use Application\Model\Categories;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Model\Twins;
use Application\Service\SpecificationsService;

class IndexController extends AbstractActionController
{
    private $cache;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var CarOfDay
     */
    private $carOfDay;

    /**
     * @var TableGateway
     */
    private $itemTable;

    public function __construct(
        $cache,
        SpecificationsService $specsService,
        CarOfDay $carOfDay,
        Categories $categories,
        Adapter $adapter
    ) {
        $this->cache = $cache;
        $this->specsService = $specsService;
        $this->carOfDay = $carOfDay;
        $this->categories = $categories;

        $this->itemTable = new TableGateway('item', $adapter);
    }

    private function brands()
    {
        $language = $this->language();

        $cacheKey = 'INDEX_BRANDS_HTML265' . $language;
        $brands = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            // cache missing
            $brandModel = new BrandModel();

            $items = $brandModel->getTopBrandsList($language);
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
                'totalBrands' => $brandModel->getTotalCount()
            ];

            $this->cache->setItem($cacheKey, $brands);
        }

        return $brands;
    }

    private function carOfDay()
    {
        $language = $this->language();
        $httpsFlag = $this->getRequest()->getUri()->getScheme();

        $carOfDay = $this->carOfDay->getCurrent();

        $carOfDayInfo = null;

        if ($carOfDay) {
            $key = 'CAR_OF_DAY_105_' . $carOfDay['item_id'] . '_' . $language . '_' . $httpsFlag;

            $carOfDayInfo = $this->cache->getItem($key, $success);
            if (! $success) {
                $carOfDayInfo = $this->carOfDay->getItemOfDay($carOfDay['item_id'], $carOfDay['user_id'], $language);

                $this->cache->setItem($key, $carOfDayInfo);
            }
        }

        return $carOfDayInfo;
    }

    private function factories()
    {
        $cacheKey = 'INDEX_FACTORIES_5';
        $factories = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $select = new Sql\Select($this->itemTable->getTable());
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
                    'item.item_type_id = ?' => DbTable\Item\Type::FACTORY,
                    new Sql\Predicate\In('product.item_type_id', [
                        DbTable\Item\Type::VEHICLE,
                        DbTable\Item\Type::ENGINE
                    ])
                ])
                ->join('item_parent', 'item.id = item_parent.parent_id', [])
                ->join(['product' => 'item'], 'item_parent.item_id = product.id', [])
                ->group('item.id')
                ->order(['new_count desc', 'count desc'])
                ->limit(8);

            $items = $this->itemTable->selectWith($select);

            $factories = [];
            foreach ($items as $item) {
                $factories[] = [
                    'id'        => $item['id'],
                    'name'      => $item['name'],
                    'count'     => $item['count'],
                    'new_count' => $item['new_count'],
                    'url'       => $this->url()->fromRoute('factories/factory', [
                        'id' => $item['id']
                    ]),
                    'new_url'   => $this->url()->fromRoute('factories/newcars', [
                        'item_id' => $item['id']
                    ])
                ];
            }

            $this->cache->setItem($cacheKey, $factories);
        }

        return $factories;
    }

    public function indexAction()
    {
        $pictures = $this->catalogue()->getPictureTable();
        $itemTable = $this->catalogue()->getItemTable();

        $language = $this->language();

        $select = $pictures->select(true)
            ->where('pictures.accept_datetime > DATE_SUB(CURDATE(), INTERVAL 3 DAY)')
            ->where('pictures.status = ?', DbTable\Picture::STATUS_ACCEPTED)
            ->order(['pictures.accept_datetime DESC', 'pictures.id DESC'])
            ->limit(6);

        $newPicturesData = $this->pic()->listData($select, [
            'width' => 3
        ]);

        // categories
        $cacheKey = 'INDEX_CATEGORY13_' . $language;
        $categories = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $categories = $this->categories->getCategoriesList(null, $language, 15, 'count');

            foreach ($categories as &$category) {
                $category['new_cars_url'] = $this->url()->fromRoute('category-newcars', [
                    'item_id' => $category['id']
                ]);
            }
            unset($category);

            $this->cache->setItem($cacheKey, $categories);
        }

        // БЛИЗНЕЦЫ
        $cacheKey = 'INDEX_INTERESTS_TWINS_BLOCK_27_' . $language;
        $twinsBlock = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $twins = new Twins();

            $twinsBrands = $twins->getBrands([
                'language' => $language,
                'limit'    => 20
            ]);

            foreach ($twinsBrands as &$brand) {
                $brand['url'] = $this->url()->fromRoute('twins/brand', [
                    'brand_catname' => $brand['catname']
                ]);
            }
            unset($brand);

            $twinsBlock = [
                'brands'     => $twinsBrands,
                'more_count' => $twins->getTotalBrandsCount()
            ];

            $this->cache->setItem($cacheKey, $twinsBlock);
        }

        $userTable = new User();

        $cacheKey = 'INDEX_SPEC_CARS_15_' . $language;
        $cars = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $itemTable = $this->catalogue()->getItemTable();

            $cars = $itemTable->fetchAll(
                $select = $itemTable->select(true)
                    ->join('attrs_user_values', 'item.id = attrs_user_values.item_id', null)
                    ->where('update_date > DATE_SUB(NOW(), INTERVAL 3 DAY)')
                    ->having('count(attrs_user_values.attribute_id) > 10')
                    ->group('item.id')
                    ->order('MAX(attrs_user_values.update_date) DESC')
                    ->limit(4)
            );

            $this->cache->setItem($cacheKey, $cars);
        }

        $specsCars = $this->car()->listData($cars, [
            'pictureFetcher' => new \Application\Model\Item\PerspectivePictureFetcher([
                'type'                 => null,
                'onlyExactlyPictures'  => false,
                'dateSort'             => false,
                'disableLargePictures' => true,
                'perspectivePageId'    => 1,
                'onlyChilds'           => []
            ]),
            'listBuilder' => new \Application\Model\Item\ListBuilder([
                'catalogue' => $this->catalogue(),
                'router'    => $this->getEvent()->getRouter(),
                'picHelper' => $this->getPluginManager()->get('pic')
            ]),
            'disableDescription'   => true,
            'callback'             => function (&$item) use ($userTable) {
                $contribPairs = $this->specsService->getContributors([$item['id']]);
                if ($contribPairs) {
                    $item['contributors'] = $userTable->fetchAll(
                        $userTable->select(true)
                            ->where('id IN (?)', array_keys($contribPairs))
                            ->where('not deleted')
                    );
                } else {
                    $item['contributors'] = [];
                }
            }
        ]);

        return [
            'brands'      => $this->brands(),
            'factories'   => $this->factories(),
            'twinsBlock'  => $twinsBlock,
            'categories'  => $categories,
            'newPictures' => $newPicturesData,
            'carOfDay'    => $this->carOfDay(),
            'specsCars'   => $specsCars,
            'mosts'       => [
                '/mosts/fastest/roadster'          => 'mosts/fastest/roadster',
                '/mosts/mighty/sedan/today'        => 'mosts/mighty/sedan/today',
                '/mosts/dynamic/universal/2000-09' => 'mosts/dynamic/universal/2000-09',
                '/mosts/heavy/truck'               => 'mosts/heavy/truck'
            ]
        ];
    }
    
    public function ngAction()
    {
        $path = $this->params('path');
        
        if ($path) {
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
