<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\ItemHydrator;
use Application\Model\CarOfDay;
use Application\Model\Catalogue;
use Application\Model\Item;
use Application\Service\SpecificationsService;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Autowp\User\Model\User;
use Exception;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Cache\Storage\StorageInterface;
use Laminas\Db\Sql;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;

use function array_keys;
use function array_merge;

/**
 * @method UserPlugin user($user = null)
 * @method string language()
 */
class IndexController extends AbstractRestfulController
{
    private StorageInterface $cache;

    private Item $item;

    private SpecificationsService $specsService;

    private ItemHydrator $itemHydrator;

    private CarOfDay $itemOfDay;

    private Catalogue $catalogue;

    public function __construct(
        StorageInterface $cache,
        Item $item,
        SpecificationsService $specsService,
        CarOfDay $itemOfDay,
        Catalogue $catalogue,
        ItemHydrator $itemHydrator
    ) {
        $this->cache        = $cache;
        $this->item         = $item;
        $this->specsService = $specsService;
        $this->itemOfDay    = $itemOfDay;
        $this->catalogue    = $catalogue;
        $this->itemHydrator = $itemHydrator;
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function specItemsAction(): JsonModel
    {
        $language = $this->language();

        $cacheKey = 'API_INDEX_SPEC_CARS_3_' . $language;
        $success  = false;
        $cars     = $this->cache->getItem($cacheKey, $success);
        if (! $success) {
            $select = $this->item->getSelect([
                'limit' => 4,
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

        $user = $this->user()->get();

        $this->itemHydrator->setOptions([
            'language'         => $language,
            'fields'           => [
                'name_html'        => true,
                'name_default'     => true,
                'description'      => true,
                'has_text'         => true,
                'produced'         => true,
                'design'           => true,
                'engine_vehicles'  => true,
                'can_edit_specs'   => true,
                'specs_route'      => true,
                'route'            => true,
                'categories'       => [
                    'catname'   => true,
                    'name_html' => true,
                ],
                'twins_groups'     => true,
                'preview_pictures' => [
                    'picture' => ['thumb_medium' => true, 'name_text' => true],
                    'url'     => true,
                ],
                'childs_count'     => true,
                'total_pictures'   => true,
            ],
            'user_id'          => $user ? $user['id'] : null,
            'preview_pictures' => [
                'perspective_page_id' => 1,
            ],
        ]);

        $items = [];
        foreach ($cars as $row) {
            $extracted                 = $this->itemHydrator->extract($row);
            $contribPairs              = $this->specsService->getContributors($row['id']);
            $extracted['contributors'] = array_keys($contribPairs);

            $items[] = $extracted;
        }

        return new JsonModel([
            'items' => $items,
        ]);
    }

    /**
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function itemOfDayAction(): JsonModel
    {
        $language = $this->language();
        /** @var Request $request */
        $request   = $this->getRequest();
        $httpsFlag = $request->getUri()->getScheme();

        $itemOfDay = $this->itemOfDay->getCurrent();

        $itemOfDayInfo = null;

        if ($itemOfDay) {
            $key = 'API_ITEM_OF_DAY_121_' . $itemOfDay['item_id'] . '_' . $language . '_' . $httpsFlag;

            $success       = false;
            $itemOfDayInfo = $this->cache->getItem($key, $success);
            if (! $success) {
                $item = $this->item->getRow([
                    'id'       => $itemOfDay['item_id'],
                    'language' => $language,
                ]);

                if ($item) {
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
                                'catname'   => true,
                            ],
                        ],
                        'user_id'  => $user ? $user['id'] : null,
                    ]);

                    $item = $this->itemHydrator->extract($item);

                    if ($item['accepted_pictures_count'] > 6 && (int) $item['item_type_id'] !== Item::CATEGORY) {
                        $cataloguePaths = $this->catalogue->getCataloguePaths($item['id'], [
                            'breakOnFirst' => true,
                        ]);

                        foreach ($cataloguePaths as $path) {
                            switch ($path['type']) {
                                case 'brand':
                                    $route = ['/', $path['brand_catname']];
                                    break;
                                case 'category':
                                    $route = ['/categories', $path['category_catname']];
                                    break;
                                case 'person':
                                    $route = ['/persons', $path['id']];
                                    break;
                                default:
                                    $route = array_merge(
                                        ['/', $path['brand_catname'], $path['car_catname']],
                                        $path['path']
                                    );
                            }
                            $item['public_route'] = $route;
                            break;
                        }
                    }
                }

                $itemOfDayInfo = [
                    'item'    => $item,
                    'user_id' => $itemOfDay['user_id'],
                ];

                $this->cache->setItem($key, $itemOfDayInfo);
            }
        }

        return new JsonModel($itemOfDayInfo);
    }
}
