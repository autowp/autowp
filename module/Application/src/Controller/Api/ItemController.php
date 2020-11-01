<?php

namespace Application\Controller\Api;

use Application\Controller\Plugin\Car;
use Application\HostManager;
use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Hydrator\Api\Strategy\Image;
use Application\Model\Brand;
use Application\Model\Catalogue;
use Application\Model\Categories;
use Application\Model\Item;
use Application\Model\ItemParent;
use Application\Model\Picture;
use Application\Model\UserItemSubscribe;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;
use ArrayAccess;
use ArrayObject;
use Autowp\Image\Storage;
use Autowp\Message\MessageService;
use Autowp\User\Controller\Plugin\User;
use Autowp\ZFComponents\Filter\FilenameSafe;
use Autowp\ZFComponents\Filter\SingleSpaces;
use Collator;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblem;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Hydrator\Strategy\StrategyInterface;
use Laminas\InputFilter\Factory;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\InputFilter\InputFilterPluginManager;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator\Adapter\DbSelect;
use Laminas\Paginator\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Uri\Uri;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Location\Coordinate;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unshift;
use function array_values;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function date;
use function explode;
use function get_object_vars;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function print_r;
use function sprintf;
use function str_replace;
use function strlen;
use function trim;
use function usort;

/**
 * @method User user($user = null)
 * @method ViewModel forbiddenAction()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 * @method string language()
 * @method Catalogue catalogue()
 * @method Car car()
 * @method void log(string $message, array $objects)
 * @method string translate(string $message, string $textDomain = 'default', $locale = null)
 */
class ItemController extends AbstractRestfulController
{
    private AbstractRestHydrator $hydrator;

    private StrategyInterface $logoHydrator;

    private InputFilter $listInputFilter;

    private InputFilter $itemInputFilter;

    private InputFilter $itemLogoPutFilter;

    private SpecificationsService $specificationsService;

    private HostManager $hostManager;

    private MessageService $message;

    private UserItemSubscribe $userItemSubscribe;

    private TableGateway $specTable;

    private ItemParent $itemParent;

    private Item $itemModel;

    private VehicleType $vehicleType;

    private InputFilterPluginManager $inputFilterManager;

    private array $collators = [];

    private SpecificationsService $specsService;

    private Storage $imageStorage;

    public function __construct(
        AbstractRestHydrator $hydrator,
        Image $logoHydrator,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        InputFilter $itemLogoPutFilter,
        SpecificationsService $specificationsService,
        ItemParent $itemParent,
        HostManager $hostManager,
        MessageService $message,
        UserItemSubscribe $userItemSubscribe,
        TableGateway $specTable,
        Item $itemModel,
        VehicleType $vehicleType,
        InputFilterPluginManager $inputFilterManager,
        SpecificationsService $specsService,
        Storage $imageStorage
    ) {
        $this->hydrator              = $hydrator;
        $this->logoHydrator          = $logoHydrator;
        $this->listInputFilter       = $listInputFilter;
        $this->itemInputFilter       = $itemInputFilter;
        $this->itemLogoPutFilter     = $itemLogoPutFilter;
        $this->specificationsService = $specificationsService;
        $this->itemParent            = $itemParent;
        $this->hostManager           = $hostManager;
        $this->message               = $message;
        $this->userItemSubscribe     = $userItemSubscribe;
        $this->specTable             = $specTable;
        $this->itemModel             = $itemModel;
        $this->vehicleType           = $vehicleType;
        $this->inputFilterManager    = $inputFilterManager;
        $this->specsService          = $specsService;
        $this->imageStorage          = $imageStorage;
    }

    private function getCollator(string $language): Collator
    {
        if (! isset($this->collators[$language])) {
            $this->collators[$language] = new Collator($language);
        }

        return $this->collators[$language];
    }

    /**
     * @return int
     */
    private function compareName(string $a, string $b, string $language)
    {
        $coll = $this->getCollator($language);
        switch ($language) {
            case 'zh':
                $aIsHan = (bool) preg_match("/^\p{Han}/u", $a);
                $bIsHan = (bool) preg_match("/^\p{Han}/u", $b);

                if ($aIsHan && ! $bIsHan) {
                    return -1;
                }

                if ($bIsHan && ! $aIsHan) {
                    return 1;
                }

                return $coll->compare($a, $b);

            default:
                return $coll->compare($a, $b);
        }
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function pathAction()
    {
        $currentCategory = $this->itemModel->getRow([
            'item_type_id' => Item::CATEGORY,
            'catname'      => (string) $this->params()->fromQuery('catname'),
        ]);

        if (! $currentCategory) {
            return $this->notFoundAction();
        }

        $breadcrumbs = [
            [
                'catname' => null,
                'item'    => $currentCategory,
            ],
        ];

        $parentCategory = $currentCategory;

        while (true) {
            $parentCategory = $this->itemModel->getRow([
                'item_type_id' => Item::CATEGORY,
                'child'        => $parentCategory['id'],
            ]);

            if (! $parentCategory) {
                break;
            }

            array_unshift($breadcrumbs, [
                'catname' => null,
                'item'    => $parentCategory,
            ]);
        }

        $path = (string) $this->params()->fromQuery('path');
        $path = $path ? explode('/', $path) : [];

        $currentCar = $currentCategory;
        foreach ($path as $pathNode) {
            $currentCar = $this->itemModel->getRow([
                'parent' => [
                    'id'           => $currentCar['id'],
                    'link_catname' => $pathNode,
                ],
            ]);

            if (! $currentCar) {
                return $this->notFoundAction();
            }

            $breadcrumbs[] = [
                'catname' => $pathNode,
                'item'    => $currentCar,
            ];
        }

        $user = $this->user()->get();

        $fields = [
            'name_html' => true,
            'name_text' => true,
            'name_only' => true,
            'catname'   => true,
        ];

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $fields,
            'user_id'  => $user ? $user['id'] : null,
        ]);

        $items = [];

        $parentId = null;
        foreach ($breadcrumbs as $idx => $item) {
            if ((int) $idx === count($breadcrumbs) - 1) {
                $fields['description'] = true;
                $this->hydrator->setFields($fields);
            }

            $items[]  = [
                'catname'   => $item['catname'],
                'parent_id' => $parentId,
                'item'      => $this->hydrator->extract($item['item']),
            ];
            $parentId = (int) $item['item']['id'];
        }

        return new JsonModel([
            'path' => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function indexAction()
    {
        $isModer = $this->user()->inheritsRole('moder');

        $user = $this->user()->get();

        $params = $this->params()->fromQuery();

        if (isset($params['type_id'])) {
            $typeId = (int) $params['type_id'];
            if ($typeId === Item::BRAND) {
                $this->listInputFilter->get('limit')->getValidatorChain()->getValidators()[1]['instance']->setMax(5000);
            }
        }

        $this->listInputFilter->setData($params);

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $select = new Sql\Select($this->itemModel->getTable()->getTable());

        $group              = [];
        $columns            = [Sql\Select::SQL_STAR];
        $itemLanguageJoined = false;

        $match = $data['descendant_pictures']
              && ($data['descendant_pictures']['status'] || $data['descendant_pictures']['owner_id']);
        if ($match) {
            $group['item.id'] = true;

            $joinColumns = [];
            if (isset($data['fields']['current_pictures_count'])) {
                $joinColumns['current_pictures_count'] = new Sql\Expression('COUNT(distinct pictures.id)');
            }

            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                ->join('picture_item', 'item_parent_cache.item_id = picture_item.item_id', [])
                ->join('pictures', 'picture_item.picture_id = pictures.id', $joinColumns);

            if (isset($data['descendant_pictures']['status']) && $data['descendant_pictures']['status']) {
                $select->where(['pictures.status' => $data['descendant_pictures']['status']]);
            }

            if (isset($data['descendant_pictures']['owner_id']) && $data['descendant_pictures']['owner_id']) {
                $select->where(['pictures.owner_id' => $data['descendant_pictures']['owner_id']]);
            }

            if (isset($data['descendant_pictures']['type_id']) && $data['descendant_pictures']['type_id']) {
                $select->where(['picture_item.type' => $data['descendant_pictures']['type_id']]);
            }
        }

        if ($data['type_id']) {
            $select->where(['item.item_type_id' => $data['type_id']]);
        }

        if ($data['catname']) {
            $select->where(['item.catname' => (string) $data['catname']]);
        }

        if ($data['related_groups_of']) {
            $groups = $this->itemModel->getRelatedCarGroups((int) $data['related_groups_of']);
            if (! $groups) {
                $groups = [0 => 0];
            }
            $select->where([new Sql\Predicate\In('item.id', array_keys($groups))]);
        }

        if ($data['name']) {
            $itemLanguageJoined = true;
            $select->join('item_language', 'item.id = item_language.item_id', []);
            $select->where(['item_language.name like ?' => $data['name']]);

            $group['item.id'] = true;
        }

        if (strlen($data['concept'])) {
            if ($data['concept']) {
                $select->where(['item.is_concept']);
            } else {
                $select->where(['NOT item.is_concept']);
            }
        }

        if (strlen($data['concept_inherit'])) {
            if ($data['concept_inherit']) {
                $select->where(['item.is_concept_inherit']);
            } else {
                $select->where(['NOT item.is_concept_inherit']);
            }
        }

        if ($data['dateful']) {
            $select->where([
                '(item.begin_year is not null or item.begin_model_year is not null)',
            ]);
        }

        if ($data['dateless']) {
            $select->where([
                'item.begin_year is null',
                'item.begin_model_year is null',
            ]);
        }

        if ($data['factories_of_brand']) {
            $select
                ->join(['fab_ipc1' => 'item_parent_cache'], 'fab_ipc1.parent_id = item.id', [])
                ->join(['fab_ipc2' => 'item_parent_cache'], 'fab_ipc1.item_id = fab_ipc2.item_id', [])
                ->where('not fab_ipc1.tuning')
                ->join(['fab_pi' => 'picture_item'], 'item.id = fab_pi.item_id', [])
                ->join(['fab_p' => 'pictures'], 'fab_pi.picture_id = fab_p.id', [])
                ->where([
                    'fab_ipc2.parent_id' => (int) $data['factories_of_brand'],
                    'fab_p.status'       => Picture::STATUS_ACCEPTED,
                    'item.item_type_id'  => Item::FACTORY,
                ]);

            $group['item.id']                                = true;
            $data['order']                                   = 'cars_count_desc';
            $data['fields']['factories_of_brand_cars_count'] = true;
            $columns['factories_of_brand_cars_count']        = new Sql\Expression('count(1)');
        }

        switch ($data['order']) {
            case 'id_asc':
                $select->order('item.id ASC');
                break;
            case 'id_desc':
                $select->order('item.id DESC');
                break;
            case 'childs_count':
                $group['item.id'] = true;
                $select
                    ->join('item_parent', 'item_parent.parent_id = item.id', [
                        'childs_count' => new Sql\Expression('count(item_parent.item_id)'),
                    ])
                    ->order('childs_count desc');
                break;
            case 'age':
                $select->order($this->catalogue()->itemOrdering());
                break;
            case 'name_length_desc':
                if (! $itemLanguageJoined) {
                    $itemLanguageJoined = true;
                    $select->join('item_language', 'item.id = item_language.item_id', []);
                }
                $select->order([new Sql\Expression('length(item_language.name)'), 'item_language.name']);
                $group['item.id'] = true;
                break;
            case 'name_nat':
                $select->order(['item.name']);
                break;
            case 'categories_first':
                $select->order(array_merge([
                    new Sql\Expression('item.item_type_id != ?', [Item::CATEGORY]),
                ], $this->catalogue()->itemOrdering()));
                break;
            case 'cars_count_desc':
                $select->order('factories_of_brand_cars_count desc');
                break;
            default:
                $select->order([
                    'item.name',
                    'item.body',
                    'item.spec_id',
                    'item.begin_order_cache',
                    'item.end_order_cache',
                ]);
                break;
        }

        if ($data['no_parent']) {
            $select
                ->join(
                    ['np_ip' => 'item_parent'],
                    'item.id = np_ip.item_id',
                    [],
                    $select::JOIN_LEFT
                )
                ->where(['np_ip.item_id IS NULL']);
        }

        if ($data['parent_id']) {
            $select
                ->join('item_parent', 'item.id = item_parent.item_id', [])
                ->where(['item_parent.parent_id' => $data['parent_id']]);
        }

        if ($data['have_common_childs_with']) {
            $select
                ->join(['ipc1' => 'item_parent_cache'], 'ipc1.parent_id = item.id', [])
                ->join(['ipc2' => 'item_parent_cache'], 'ipc1.item_id = ipc2.item_id', [])
                ->where(['ipc2.parent_id' => (int) $data['have_common_childs_with']]);

            $group['item.id'] = true;
        }

        if ($data['ancestor_id']) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', [])
                ->where([
                    'item_parent_cache.parent_id' => $data['ancestor_id'],
                    'item_parent_cache.item_id <> item_parent_cache.parent_id',
                ]);
        }

        if ($data['vehicle_type_id']) {
            if ($data['vehicle_type_id'] === 'empty') {
                $select
                    ->join(
                        'vehicle_vehicle_type',
                        'item.id = vehicle_vehicle_type.vehicle_id',
                        [],
                        $select::JOIN_LEFT
                    )
                    ->where(['vehicle_vehicle_type.vehicle_id is null']);
            } else {
                $select
                    ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', [])
                    ->where(['vehicle_vehicle_type.vehicle_type_id' => $data['vehicle_type_id']]);
            }
        }

        if ($isModer) {
            if ($data['name_exclude']) {
                $select
                    ->join(['ile' => 'item_language'], 'item.id = ile.item_id', [])
                    ->where(['ile.name not like ?' => $data['name_exclude']]);
            }

            $id = (int) $this->params()->fromQuery('id');
            if ($id) {
                $select->where(['item.id' => $id]);
            }

            if ($data['descendant']) {
                $group['item.id'] = true;
                $select->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                    ->where(['item_parent_cache.item_id' => $data['descendant']]);
            }

            if ($data['vehicle_childs_type_id']) {
                $group['item.id'] = true;
                $select
                    ->join(
                        ['cpc_childs' => 'item_parent_cache'],
                        'item.id = cpc_childs.parent_id',
                        []
                    )
                    ->join(
                        ['vvt_child' => 'vehicle_vehicle_type'],
                        'cpc_childs.item_id = vvt_child.vehicle_id',
                        []
                    )
                    ->join('car_types_parents', 'vvt_child.vehicle_type_id = car_types_parents.id', [])
                    ->where(['car_types_parents.parent_id' => $data['vehicle_childs_type_id']]);
            }

            if ($data['spec']) {
                $select->where(['item.spec_id' => $data['spec']]);
            }

            if ($data['from_year']) {
                $select->where(['item.begin_year' => $data['from_year']]);
            }

            if ($data['to_year']) {
                $select->where(['item.end_year' => $data['to_year']]);
            }

            if ($data['text']) {
                if (! $itemLanguageJoined) {
                    $itemLanguageJoined = true;
                    $select->join('item_language', 'item.id = item_language.item_id', []);
                }
                $select
                    ->join('textstorage_text', 'item_language.text_id = textstorage_text.id', [])
                    ->where(['textstorage_text.text like ?' => '%' . $data['text'] . '%']);

                $group['item.id'] = true;
            }

            if ($data['suggestions_to']) {
                $subSelect = new Sql\Select('item');
                $subSelect->columns(['id'])
                    ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                    ->where([
                        'item.item_type_id'         => Item::BRAND,
                        'item_parent_cache.item_id' => $data['suggestions_to'],
                    ]);

                $select
                    ->join(['ils' => 'item_language'], 'item.id = ils.item_id', [])
                    ->join(['ils2' => 'item_language'], new Sql\Predicate\Expression('INSTR(ils.name, ils2.name)'), [])
                    ->where([
                        'item.item_type_id' => Item::BRAND,
                        'ils2.item_id'      => $data['suggestions_to'],
                        new Sql\Predicate\In('item.id', $subSelect),
                    ]);

                $group['item.id'] = true;
            }

            if ($data['have_childs_of_type']) {
                $select
                    ->join(['ipc3' => 'item_parent_cache'], 'item.id = ipc3.parent_id', [])
                    ->join(['child' => 'item'], 'ipc3.item_id = child.id', [])
                    ->where(['child.item_type_id' => (int) $data['have_childs_of_type']]);

                $group['item.id'] = true;
            }

            if ($data['have_childs_with_parent_of_type']) {
                $select
                    ->join(['ipc4' => 'item_parent_cache'], 'item.id = ipc4.parent_id', [])
                    ->join(['ip5' => 'item_parent'], 'ipc4.item_id = ip5.item_id', [])
                    ->join(['child2' => 'item'], 'ip5.parent_id = child2.id', [])
                    ->where(['child2.item_type_id' => (int) $data['have_childs_with_parent_of_type']]);

                $group['item.id'] = true;
            }

            if ($data['engine_id']) {
                $select->where(['item.engine_item_id' => (int) $data['engine_id']]);
            }

            if ($data['is_group']) {
                $select->where(['item.is_group']);
            }

            if ($data['autocomplete']) {
                $query = $data['autocomplete'];

                $beginYear      = false;
                $endYear        = false;
                $today          = false;
                $body           = false;
                $beginModelYear = null;
                $endModelYear   = null;

                $pattern = "|^"
                    . "(([0-9]{4})([-–]([^[:space:]]{2,4}))?[[:space:]]+)?(.*?)( \((.+)\))?( '([0-9]{4})(–(.+))?)?"
                    . "$|isu";

                if (preg_match($pattern, $query, $match)) {
                    $query          = trim($match[5]);
                    $body           = isset($match[7]) ? trim($match[7]) : null;
                    $beginYear      = isset($match[9]) ? (int) $match[9] : null;
                    $endYear        = $match[11] ?? null;
                    $beginModelYear = isset($match[2]) ? (int) $match[2] : null;
                    $endModelYear   = $match[4] ?? null;

                    if ($endYear === 'н.в.') {
                        $today   = true;
                        $endYear = false;
                    } else {
                        $eyLength = strlen($endYear);
                        if ($eyLength) {
                            if ($eyLength === 2) {
                                $endYear = $beginYear - $beginYear % 100 + $endYear;
                            } else {
                                $endYear = (int) $endYear;
                            }
                        } else {
                            $endYear = false;
                        }
                    }

                    if ($endModelYear === 'н.в.') {
                        $today        = true;
                        $endModelYear = false;
                    } else {
                        $eyLength = strlen($endModelYear);
                        if ($eyLength) {
                            if ($eyLength === 2) {
                                $endModelYear = $beginModelYear - $beginModelYear % 100 + $endModelYear;
                            } else {
                                $endModelYear = (int) $endModelYear;
                            }
                        } else {
                            $endModelYear = false;
                        }
                    }
                }

                $specId = null;
                if ($query) {
                    $specRow = currentFromResultSetInterface($this->specTable->select([
                        new Sql\Predicate\Expression('INSTR(?, short_name)', $query),
                    ]));

                    if ($specRow) {
                        $specId = $specRow['id'];
                        $query  = trim(str_replace($specRow['short_name'], '', $query));
                    }
                }

                if (! $itemLanguageJoined) {
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $itemLanguageJoined = true;
                    $select->join('item_language', 'item.id = item_language.item_id', []);
                }

                $select->where(['item_language.name like ?' => $query . '%']);

                if ($specId) {
                    $select->where(['spec_id' => $specId]);
                }

                if ($beginYear) {
                    $select->where(['item.begin_year' => $beginYear]);
                }
                if ($today) {
                    $select->where(['item.today']);
                } elseif ($endYear) {
                    $select->where(['item.end_year' => $endYear]);
                }
                if ($body) {
                    $select->where(['item.body like ?' => $body . '%']);
                }

                if ($beginModelYear) {
                    $select->where(['item.begin_model_year' => $beginModelYear]);
                }

                if ($endModelYear) {
                    $select->where(['item.end_model_year' => $endModelYear]);
                }

                $group['item.id'] = true;
            }

            if ($data['exclude_self_and_childs']) {
                $select
                    ->join(
                        ['esac' => 'item_parent_cache'],
                        new Sql\Predicate\Expression(
                            'item.id = esac.item_id and esac.parent_id = ?',
                            [$data['exclude_self_and_childs']]
                        ),
                        [],
                        $select::JOIN_LEFT
                    )
                    ->where(['esac.item_id is null']);
            }

            if ($data['parent_types_of']) {
                $typeId = $data['parent_types_of'];

                $allowedItemTypes = [0];
                switch ($typeId) {
                    case Item::VEHICLE:
                        $allowedItemTypes = [
                            Item::VEHICLE,
                            Item::CATEGORY,
                            Item::TWINS,
                            Item::BRAND,
                            Item::FACTORY,
                        ];
                        break;
                    case Item::ENGINE:
                        $allowedItemTypes = [
                            Item::ENGINE,
                            Item::CATEGORY,
                            Item::TWINS,
                            Item::BRAND,
                            Item::FACTORY,
                        ];
                        break;
                    case Item::BRAND:
                        $allowedItemTypes = [
                            Item::CATEGORY,
                            Item::BRAND,
                        ];
                        break;
                    case Item::CATEGORY:
                        $allowedItemTypes = [
                            Item::CATEGORY,
                        ];
                        break;
                }

                $select->where([new Sql\Predicate\In('item.item_type_id', $allowedItemTypes)]);
            }
        }

        if ($group) {
            $select->group(array_keys($group));
        }

        $select->columns($columns);

        try {
            /** @var Adapter $adapter */
            $adapter   = $this->itemModel->getTable()->getAdapter();
            $paginator = new Paginator(new DbSelect($select, $adapter));

            $limit = $data['limit'] ? $data['limit'] : 1;

            $paginator
                ->setItemCountPerPage($limit)
                ->setCurrentPageNumber($data['page']);
        } catch (Exception $e) {
            throw new Exception(
                'SQL Error : '
                . print_r($this->params()->fromQuery(), true) . "\n"
                . $select->getSqlString($this->itemModel->getTable()->getAdapter()->getPlatform())
            );
        }

        $previewPictures = [];
        if (isset($data['preview_pictures']['type_id']) && $data['preview_pictures']['type_id']) {
            $previewPictures['type_id'] = $data['preview_pictures']['type_id'];
        }

        $this->hydrator->setOptions([
            'language'         => $this->language(),
            'fields'           => $data['fields'],
            'user_id'          => $user ? $user['id'] : null,
            'preview_pictures' => $previewPictures,
            'route_brand_id'   => $data['route_brand_id'],
        ]);

        $rows = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $rows[] = $row;
        }

        if ($data['order'] === 'name_nat') {
            $language = $this->language();
            usort($rows, function ($a, $b) use ($language) {
                if ($a['position'] !== $b['position']) {
                    return $a['position'] < $b['position'] ? -1 : 1;
                }

                return $this->compareName($a['name'], $b['name'], $language);
            });
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function alphaAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $select = new Sql\Select($this->itemModel->getTable()->getTable());
        $select->columns(['char' => new Sql\Expression('UPPER(LEFT(name, 1))')])
            ->quantifier($select::QUANTIFIER_DISTINCT)
            ->order('char');

        $chars = [];
        foreach ($this->itemModel->getTable()->selectWith($select) as $row) {
            $chars[] = $row['char'];
        }

        $groups = [
            'numbers' => [],
            'english' => [],
            'other'   => [],
        ];

        foreach ($chars as $char) {
            if (preg_match('|^["0-9-]$|isu', $char)) {
                $groups['numbers'][] = $char;
            } elseif (preg_match('|^[A-Za-z]$|isu', $char)) {
                $groups['english'][] = $char;
            } else {
                $groups['other'][] = $char;
            }
        }

        return new JsonModel([
            'groups' => array_values($groups),
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function itemAction()
    {
        $user = $this->user()->get();

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $row = $this->itemModel->getRow(['id' => (int) $this->params('id')]);

        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null,
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }

    private function getInputFilter(int $itemTypeId, bool $post, ?int $itemId): InputFilterInterface
    {
        $select = new Sql\Select($this->specTable->getTable());
        $select->columns(['id']);
        $specOptions = [];
        foreach ($this->specTable->selectWith($select) as $row) {
            $specOptions[] = (int) $row['id'];
        }
        $specOptions[] = 'inherited';

        $spec = [
            'name'                      => [
                'required'   => $post,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 2,
                            'max' => Item::MAX_NAME,
                        ],
                    ],
                ],
            ],
            'full_name'                 => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'max' => Brand::MAX_FULLNAME,
                        ],
                    ],
                ],
            ],
            'catname'                   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                    ['name' => FilenameSafe::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 3,
                            'max' => 100,
                        ],
                    ],
                    [
                        'name'    => 'ItemCatnameNotExists',
                        'options' => [
                            'exclude' => $itemId,
                        ],
                    ],
                ],
            ],
            'body'                      => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                ],
                'validators' => [
                    [
                        'name'    => 'StringLength',
                        'options' => [
                            'min' => 1,
                            'max' => 20,
                        ],
                    ],
                ],
            ],
            'spec_id'                   => [
                'required'   => false,
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => $specOptions,
                        ],
                    ],
                ],
            ],
            'begin_model_year'          => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1500,
                            'max' => 2100,
                        ],
                    ],
                ],
            ],
            'begin_model_year_fraction' => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => ['¼', '½', '¾'],
                        ],
                    ],
                ],
            ],
            'end_model_year'            => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1500,
                            'max' => 2100,
                        ],
                    ],
                ],
            ],
            'end_model_year_fraction'   => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    [
                        'name'    => 'InArray',
                        'options' => [
                            'haystack' => ['¼', '½', '¾'],
                        ],
                    ],
                ],
            ],
            'begin_year'                => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1500,
                            'max' => 2100,
                        ],
                    ],
                ],
            ],
            'begin_month'               => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'end_year'                  => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name'    => 'Between',
                        'options' => [
                            'min' => 1500,
                            'max' => 2100,
                        ],
                    ],
                ],
            ],
            'end_month'                 => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'today'                     => [
                'required'    => false,
                'allow_empty' => true,
            ],
            'is_concept'                => [
                'required'    => false,
                'allow_empty' => true,
            ],
            'is_group'                  => [
                'required'    => false,
                'allow_empty' => true,
            ],
            'lat'                       => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'lng'                       => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim'],
                ],
            ],
            'produced'                  => [
                'required'   => false,
                'filters'    => [
                    ['name' => 'StringTrim'],
                ],
                'validators' => [
                    ['name' => 'Digits'],
                ],
            ],
            'produced_exactly'          => [
                'required'    => false,
                'allow_empty' => true,
            ],
            'subscription'              => [
                'required'    => false,
                'allow_empty' => true,
            ],
            'engine_id'                 => [
                'required'    => false,
                'allow_empty' => true,
                'filters'     => [
                    ['name' => 'StringTrim'],
                ],
            ],
        ];

        $pointFields = in_array($itemTypeId, [
            Item::FACTORY,
            Item::MUSEUM,
        ]);
        if (! $pointFields) {
            unset($spec['lat'], $spec['lng']);
        }

        if ($itemTypeId !== Item::BRAND) {
            unset($spec['full_name']);
        }

        if ($itemTypeId !== Item::VEHICLE) {
            unset($spec['engine_id']);
        }

        if (! in_array($itemTypeId, [Item::CATEGORY, Item::BRAND])) {
            unset($spec['catname']);
        }

        if (! in_array($itemTypeId, [Item::VEHICLE, Item::ENGINE])) {
            unset($spec['is_group']);
            unset($spec['is_concept']);
            unset($spec['produced'], $spec['produced_exactly']);
            unset($spec['begin_model_year'], $spec['end_model_year']);
            unset($spec['begin_model_year_fraction'], $spec['end_model_year_fraction']);
            unset($spec['spec_id']);
            unset($spec['body']);
        }

        $factory = new Factory();
        $this->inputFilterManager->populateFactoryPluginManagers($factory);
        return $factory->createInputFilter($spec);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function postAction()
    {
        if (! $this->user()->isAllowed('car', 'add')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        /** @var Request $request */
        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        if (! isset($data['item_type_id'])) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid item_type_id'));
        }

        $itemTypeId = (int) $data['item_type_id'];

        $inputFilter = $this->getInputFilter($itemTypeId, true, null);

        $fields = ['name'];
        switch ($itemTypeId) {
            case Item::CATEGORY:
            case Item::BRAND:
                $fields[] = 'catname';
                break;
        }
        foreach (array_keys($data) as $key) {
            if ($inputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        $inputFilter->setValidationGroup($fields);

        $inputFilter->setData($data);
        if (! $inputFilter->isValid()) {
            return $this->inputFilterResponse($inputFilter);
        }

        $values = $inputFilter->getValues();

        $set = [
            'item_type_id'       => $itemTypeId,
            'body'               => '',
            'produced_exactly'   => 0,
            'is_concept_inherit' => 1,
            'spec_inherit'       => 1,
            'is_concept'         => 0,
            'add_datetime'       => new Sql\Expression('NOW()'),
        ];

        if (array_key_exists('name', $values)) {
            $set['name'] = $values['name'];
        }

        if (array_key_exists('full_name', $values)) {
            $set['full_name'] = $values['full_name'] ? $values['full_name'] : null;
        }

        if (array_key_exists('body', $values)) {
            $set['body'] = (string) $values['body'];
        }

        if (array_key_exists('begin_year', $values)) {
            $set['begin_year'] = $values['begin_year'] ? $values['begin_year'] : null;
        }

        if (array_key_exists('begin_month', $values)) {
            $set['begin_month'] = $values['begin_month'] ? $values['begin_month'] : null;
        }

        if (array_key_exists('end_year', $values)) {
            $endYear         = $values['end_year'] ? $values['end_year'] : null;
            $set['end_year'] = $endYear;

            if ($endYear) {
                if ($endYear < date('Y')) {
                    $values['today'] = 0;
                }
            }
        }

        if (array_key_exists('end_month', $values)) {
            $set['end_month'] = $values['end_month'] ? $values['end_month'] : null;
        }

        if (array_key_exists('today', $values)) {
            if (strlen($values['today'])) {
                $set['today'] = $values['today'] ? 1 : 0;
            } else {
                $set['today'] = null;
            }
        }

        if (array_key_exists('begin_model_year', $values)) {
            $set['begin_model_year'] = $values['begin_model_year'] ? $values['begin_model_year'] : null;
        }

        if (array_key_exists('end_model_year', $values)) {
            $set['end_model_year'] = $values['end_model_year'] ? $values['end_model_year'] : null;
        }

        if (array_key_exists('begin_model_year_fraction', $values)) {
            $set['begin_model_year_fraction'] = $values['begin_model_year_fraction']
                ? $values['begin_model_year_fraction']
                : null;
        }

        if (array_key_exists('end_model_year_fraction', $values)) {
            $set['end_model_year_fraction'] = $values['end_model_year_fraction']
                ? $values['end_model_year_fraction']
                : null;
        }

        if (array_key_exists('is_concept', $values)) {
            $set['is_concept_inherit'] = ((string) $values['is_concept']) === 'inherited' ? 1 : 0;
            if (! $set['is_concept_inherit']) {
                $set['is_concept'] = $values['is_concept'] ? 1 : 0;
            }
        }

        if (array_key_exists('catname', $values)) {
            if (! $values['catname'] || $values['catname'] === '_') {
                $filter            = new FilenameSafe();
                $values['catname'] = $filter->filter($values['name']);
            }

            $set['catname'] = $values['catname'];
        } else {
            $filter            = new FilenameSafe();
            $values['catname'] = $filter->filter($values['name']);
        }

        if (array_key_exists('produced', $values)) {
            $set['produced'] = strlen($values['produced']) ? (int) $values['produced'] : null;
        }

        if (array_key_exists('produced_exactly', $values)) {
            $set['produced_exactly'] = $values['produced_exactly'] ? 1 : 0;
        }

        switch ($itemTypeId) {
            case Item::VEHICLE:
            case Item::ENGINE:
                if (array_key_exists('is_group', $values)) {
                    $set['is_group'] = $values['is_group'] ? 1 : 0;
                }
                break;
            case Item::CATEGORY:
            case Item::TWINS:
            case Item::BRAND:
            case Item::FACTORY:
            case Item::MUSEUM:
            case Item::PERSON:
            case Item::COPYRIGHT:
                $set['is_group'] = 1;
                break;
            default:
                return $this->notFoundAction();
        }

        if (array_key_exists('spec_id', $values)) {
            if ($values['spec_id'] === 'inherited') {
                $set['spec_inherit'] = 1;
            } else {
                $set['spec_inherit'] = 0;
                $set['spec_id']      = $values['spec_id'] ? (int) $values['spec_id'] : null;
            }
        }

        $this->itemModel->getTable()->insert($set);
        $itemId = $this->itemModel->getTable()->getLastInsertValue();

        if (array_key_exists('lat', $values) && array_key_exists('lng', $values)) {
            $point = null;
            if (strlen($values['lat']) && strlen($values['lng'])) {
                $point = new Coordinate($values['lat'], $values['lng']);
            }
            $this->itemModel->setPoint($itemId, $point);
        }

        if (array_key_exists('name', $values)) {
            $this->itemModel->setLanguageName($itemId, 'xx', $values['name']);
        }

        $this->itemModel->updateOrderCache($itemId);

        $this->itemParent->rebuildCache($itemId);

        $this->vehicleType->refreshInheritanceFromParents($itemId);

        $this->userItemSubscribe->subscribe($user['id'], $itemId);

        $this->itemModel->updateInteritance($itemId);

        $this->specificationsService->updateInheritedValues($itemId);

        $item = $this->itemModel->getRow(['id' => $itemId]);
        $this->log(sprintf(
            'Создан новый автомобиль %s',
            htmlspecialchars($this->car()->formatName($item, 'en'))
        ), [
            'items' => $itemId,
        ]);

        $url = $this->url()->fromRoute('api/item/item/get', [
            'id' => $itemId,
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $url);
        return $response->setStatusCode(Response::STATUS_CODE_201);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function putAction()
    {
        if (! $this->user()->isAllowed('car', 'edit_meta')) {
            return $this->forbiddenAction();
        }

        $item = $this->itemModel->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $request = $this->getRequest();
        $data    = (array) $this->processBodyContent($request);

        $inputFilter = $this->getInputFilter($item['item_type_id'], false, $item['id']);

        $fields = [];
        foreach (array_keys($data) as $key) {
            if ($inputFilter->has($key)) {
                $fields[] = $key;
            }
        }

        if (! $fields) {
            return new ApiProblemResponse(new ApiProblem(400, 'Not params'));
        }

        $inputFilter->setValidationGroup($fields);

        $inputFilter->setData($data);
        if (! $inputFilter->isValid()) {
            return $this->inputFilterResponse($inputFilter);
        }

        $oldData = $item;

        $values = $inputFilter->getValues();

        $set          = [];
        $updateActual = false;
        $notifyMeta   = false;
        $subscribe    = false;

        if (array_key_exists('subscription', $values)) {
            if ($values['subscription']) {
                $subscribe = true;
            } else {
                $this->userItemSubscribe->unsubscribe($user['id'], $item['id']);
            }
        }

        if (array_key_exists('name', $values)) {
            $notifyMeta  = true;
            $subscribe   = true;
            $set['name'] = $values['name'];
            $this->itemModel->setLanguageName($item['id'], 'xx', $values['name']);
        }

        if (array_key_exists('full_name', $values)) {
            $notifyMeta       = true;
            $subscribe        = true;
            $set['full_name'] = $values['full_name'] ? $values['full_name'] : null;
        }

        if (array_key_exists('body', $values)) {
            $notifyMeta  = true;
            $set['body'] = (string) $values['body'];
        }

        if (array_key_exists('begin_year', $values)) {
            $notifyMeta        = true;
            $subscribe         = true;
            $set['begin_year'] = $values['begin_year'] ? $values['begin_year'] : null;
        }

        if (array_key_exists('begin_month', $values)) {
            $notifyMeta         = true;
            $subscribe          = true;
            $set['begin_month'] = $values['begin_month'] ? $values['begin_month'] : null;
        }

        if (array_key_exists('end_year', $values)) {
            $notifyMeta      = true;
            $subscribe       = true;
            $endYear         = $values['end_year'] ? $values['end_year'] : null;
            $set['end_year'] = $endYear;

            if ($endYear && $endYear < date('Y')) {
                $values['today'] = false;
            }
        }

        if (array_key_exists('end_month', $values)) {
            $notifyMeta       = true;
            $subscribe        = true;
            $set['end_month'] = $values['end_month'] ? $values['end_month'] : null;
        }

        if (array_key_exists('today', $values)) {
            $notifyMeta = true;
            $subscribe  = true;
            if (is_string($values['today'])) {
                $values['today'] = strlen($values['today']) ? (bool) strlen($values['today']) : null;
            }
            if ($values['today'] !== null) {
                $set['today'] = $values['today'] ? 1 : 0;
            } else {
                $set['today'] = null;
            }
        }

        if (array_key_exists('begin_model_year', $values)) {
            $notifyMeta              = true;
            $subscribe               = true;
            $set['begin_model_year'] = $values['begin_model_year'] ? $values['begin_model_year'] : null;
        }

        if (array_key_exists('end_model_year', $values)) {
            $notifyMeta            = true;
            $subscribe             = true;
            $set['end_model_year'] = $values['end_model_year'] ? $values['end_model_year'] : null;
        }

        if (array_key_exists('begin_model_year_fraction', $values)) {
            $notifyMeta                       = true;
            $subscribe                        = true;
            $set['begin_model_year_fraction'] = $values['begin_model_year_fraction']
                ? $values['begin_model_year_fraction']
                : null;
        }

        if (array_key_exists('end_model_year_fraction', $values)) {
            $notifyMeta                     = true;
            $subscribe                      = true;
            $set['end_model_year_fraction'] = $values['end_model_year_fraction']
                ? $values['end_model_year_fraction']
                : null;
        }

        if (array_key_exists('is_concept', $values)) {
            $notifyMeta                = true;
            $subscribe                 = true;
            $set['is_concept_inherit'] = $values['is_concept'] === 'inherited' ? 1 : 0;
            if (! $set['is_concept_inherit']) {
                $set['is_concept'] = $values['is_concept'] ? 1 : 0;
            }
        }

        if (array_key_exists('catname', $values)) {
            $notifyMeta = true;
            $subscribe  = true;
            if (! $values['catname']) {
                $filter            = new FilenameSafe();
                $values['catname'] = $filter->filter($values['name']);
            }

            $set['catname'] = $values['catname'];
        }

        if (array_key_exists('produced', $values)) {
            $notifyMeta      = true;
            $subscribe       = true;
            $set['produced'] = strlen($values['produced']) ? (int) $values['produced'] : null;
        }

        if (array_key_exists('produced_exactly', $values)) {
            $notifyMeta              = true;
            $subscribe               = true;
            $set['produced_exactly'] = $values['produced_exactly'] ? 1 : 0;
        }

        switch ($item['item_type_id']) {
            case Item::VEHICLE:
            case Item::ENGINE:
                if (array_key_exists('is_group', $values)) {
                    $notifyMeta    = true;
                    $subscribe     = true;
                    $hasChildItems = $this->itemParent->hasChildItems($item['id']);

                    if ($hasChildItems) {
                        $set['is_group'] = 1;
                    } else {
                        $set['is_group'] = $values['is_group'] ? 1 : 0;
                    }
                }
                break;
            case Item::CATEGORY:
            case Item::TWINS:
            case Item::BRAND:
            case Item::FACTORY:
            case Item::MUSEUM:
                $set['is_group'] = 1;
                break;
        }

        if (array_key_exists('spec_id', $values)) {
            $notifyMeta = true;
            $subscribe  = true;
            if ($values['spec_id'] === 'inherited') {
                $set['spec_inherit'] = 1;
            } else {
                $set['spec_inherit'] = 0;
                $set['spec_id']      = $values['spec_id'] ? (int) $values['spec_id'] : null;
            }
        }

        if (array_key_exists('engine_id', $values)) {
            if (! $this->user()->isAllowed('specifications', 'edit-engine')) {
                return $this->forbiddenAction();
            }

            if (! $this->user()->isAllowed('specifications', 'edit')) {
                return $this->forbiddenAction();
            }

            $updateActual = true;
            $subscribe    = true;

            if ($values['engine_id'] === 'inherited') {
                $set['engine_inherit'] = 1;
                $set['engine_item_id'] = null;

                $message = sprintf(
                    'У автомобиля %s установлено наследование двигателя',
                    htmlspecialchars($this->car()->formatName($item, 'en'))
                );
                $this->log($message, [
                    'items' => $item['id'],
                ]);

                $user = $this->user()->get();

                foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
                    if ($subscriber && ((int) $subscriber['id'] !== (int) $user['id'])) {
                        $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                        $message = sprintf(
                            $this->translate(
                                'pm/user-%s-set-inherited-vehicle-engine-%s-%s',
                                'default',
                                $subscriber['language']
                            ),
                            $this->userUrl($user, $uri),
                            $this->car()->formatName($item, $subscriber['language']),
                            $this->itemModerUrl($item['id'], $uri)
                        );

                        $this->message->send(null, $subscriber['id'], $message);
                    }
                }
            } elseif ($values['engine_id'] === null || $values['engine_id'] === '') {
                $engine = $this->itemModel->getRow(['id' => (int) $item['engine_item_id']]);

                $set['engine_inherit'] = 0;
                $set['engine_item_id'] = null;

                if ($engine) {
                    $message = sprintf(
                        'У автомобиля %s убран двигатель (был %s)',
                        htmlspecialchars($this->car()->formatName($item, 'en')),
                        htmlspecialchars($engine['name'])
                    );
                    $this->log($message, [
                        'items' => $item['id'],
                    ]);

                    $user = $this->user()->get();

                    foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
                        if ($subscriber && ((int) $subscriber['id'] !== (int) $user['id'])) {
                            $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                            $message = sprintf(
                                $this->translate(
                                    'pm/user-%s-canceled-vehicle-engine-%s-%s-%s',
                                    'default',
                                    $subscriber['language']
                                ),
                                $this->userUrl($user, $uri),
                                $engine['name'],
                                $this->car()->formatName($item, $subscriber['language']),
                                $this->itemModerUrl($item['id'], $uri)
                            );

                            $this->message->send(null, $subscriber['id'], $message);
                        }
                    }
                }
            } else {
                $engine = $this->itemModel->getRow([
                    'id'           => (int) $values['engine_id'],
                    'item_type_id' => Item::ENGINE,
                ]);
                if (! $engine) {
                    return $this->notFoundAction();
                }

                $set['engine_inherit'] = 0;
                $set['engine_item_id'] = $values['engine_id'];

                $message = sprintf(
                    'Автомобилю %s назначен двигатель %s',
                    htmlspecialchars($this->car()->formatName($item, 'en')),
                    htmlspecialchars($engine['name'])
                );
                $this->log($message, [
                    'items' => $item['id'],
                ]);

                foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
                    if ($subscriber && ((int) $subscriber['id'] !== (int) $user['id'])) {
                        $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                        $message = sprintf(
                            $this->translate(
                                'pm/user-%s-set-vehicle-engine-%s-%s-%s',
                                'default',
                                $subscriber['language']
                            ),
                            $this->userUrl($user, $uri),
                            $engine['name'],
                            $this->car()->formatName($item, $subscriber['language']),
                            $this->itemModerUrl($item['id'], $uri)
                        );

                        $this->message->send(null, $subscriber['id'], $message);
                    }
                }
            }
        }

        if ($set) {
            $this->itemModel->getTable()->update($set, [
                'id' => $item['id'],
            ]);
        }

        if (isset($values['lat'], $values['lng'])) {
            $subscribe = true;
            $point     = null;
            if (strlen($values['lat']) && strlen($values['lng'])) {
                $point = new Coordinate($values['lat'], $values['lng']);
            }
            $this->itemModel->setPoint($item['id'], $point);
        }

        $this->itemModel->updateInteritance($item['id']);
        $this->itemModel->updateOrderCache($item['id']);

        $this->itemParent->refreshAutoByVehicle($item['id']);

        if ($updateActual) {
            $this->specsService->updateActualValues($item['id']);
        }

        if ($subscribe) {
            $this->userItemSubscribe->subscribe($user['id'], $item['id']);
        }

        if ($notifyMeta) {
            $newData     = $item = $this->itemModel->getRow(['id' => $item['id']]);
            $htmlChanges = [];
            foreach ($this->buildChangesMessage($oldData, $newData, 'en') as $line) {
                $htmlChanges[] = htmlspecialchars($line);
            }

            $message = sprintf(
                'Редактирование мета-информации автомобиля %s',
                htmlspecialchars($this->car()->formatName($item, 'en'))
                . ( count($htmlChanges) ? '<p>' . implode('<br />', $htmlChanges) . '</p>' : '')
            );
            $this->log($message, [
                'items' => $item['id'],
            ]);

            $user = $this->user()->get();
            foreach ($this->userItemSubscribe->getItemSubscribers($item['id']) as $subscriber) {
                if ($subscriber && ((int) $subscriber['id'] !== (int) $user['id'])) {
                    $uri = $this->hostManager->getUriByLanguage($subscriber['language']);

                    $changes = $this->buildChangesMessage($oldData, $newData, $subscriber['language']);

                    $message = sprintf(
                        $this->translate(
                            'pm/user-%s-edited-vehicle-meta-data-%s-%s-%s',
                            'default',
                            $subscriber['language']
                        ),
                        $this->userUrl($user, $uri),
                        $this->car()->formatName($item, $subscriber['language']),
                        $this->itemModerUrl($item['id'], $uri),
                        count($changes) ? implode("\n", $changes) : ''
                    );

                    $this->message->send(null, $subscriber['id'], $message);
                }
            }
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }

    /**
     * @param array|ArrayObject $user
     */
    private function userUrl($user, Uri $uri): string
    {
        $u = clone $uri;
        $u->setPath('/users/' . ($user['identity'] ? $user['identity'] : 'user' . $user['id']));

        return $u->toString();
    }

    private function itemModerUrl(int $itemId, Uri $uri): string
    {
        $u = clone $uri;
        $u->setPath('/moder/items/item/' . $itemId);

        return $u->toString();
    }

    /**
     * @param array|ArrayAccess $oldData
     * @param array|ArrayAccess $newData
     */
    private function buildChangesMessage($oldData, $newData, string $language): array
    {
        $fields = [
            'name'                      => ['str', 'moder/vehicle/changes/name-%s-%s'],
            'body'                      => ['str', 'moder/vehicle/changes/body-%s-%s'],
            'begin_year'                => ['int', 'moder/vehicle/changes/from/year-%s-%s'],
            'begin_month'               => ['int', 'moder/vehicle/changes/from/month-%s-%s'],
            'end_year'                  => ['int', 'moder/vehicle/changes/to/year-%s-%s'],
            'end_month'                 => ['int', 'moder/vehicle/changes/to/month-%s-%s'],
            'today'                     => ['bool', 'moder/vehicle/changes/to/today-%s-%s'],
            'produced'                  => ['int', 'moder/vehicle/changes/produced/count-%s-%s'],
            'produced_exactly'          => ['bool', 'moder/vehicle/changes/produced/exactly-%s-%s'],
            'is_concept'                => ['bool', 'moder/vehicle/changes/is-concept-%s-%s'],
            'is_group'                  => ['bool', 'moder/vehicle/changes/is-group-%s-%s'],
            'begin_model_year'          => ['int', 'moder/vehicle/changes/model-years/from-%s-%s'],
            'end_model_year'            => ['int', 'moder/vehicle/changes/model-years/to-%s-%s'],
            'begin_model_year_fraction' => ['int', 'moder/vehicle/changes/model-years-fraction/from-%s-%s'],
            'end_model_year_fraction'   => ['int', 'moder/vehicle/changes/model-years-fraction/to-%s-%s'],
            'spec_id'                   => ['spec_id', 'moder/vehicle/changes/spec-%s-%s'],
            //'vehicle_type_id'  => ['vehicle_type_id', 'moder/vehicle/changes/car-type-%s-%s']
        ];

        $changes = [];
        foreach ($fields as $field => $info) {
            $message = $this->translate($info[1], 'default', $language);
            switch ($info[0]) {
                case 'int':
                    $old = $oldData[$field] === null ? null : (int) $oldData[$field];
                    $new = $newData[$field] === null ? null : (int) $newData[$field];
                    if ($old !== $new) {
                        $changes[] = sprintf($message, $old, $new);
                    }
                    break;
                case 'str':
                    $old = $oldData[$field] === null ? null : (string) $oldData[$field];
                    $new = $newData[$field] === null ? null : (string) $newData[$field];
                    if ($old !== $new) {
                        $changes[] = sprintf($message, $old, $new);
                    }
                    break;
                case 'bool':
                    $old = $oldData[$field] === null
                        ? null
                        : $this->translate($oldData[$field]
                            ? 'moder/vehicle/changes/boolean/true'
                            : 'moder/vehicle/changes/boolean/false');
                    $new = $newData[$field] === null
                        ? null
                        : $this->translate($newData[$field]
                            ? 'moder/vehicle/changes/boolean/true'
                            : 'moder/vehicle/changes/boolean/false');
                    if ($old !== $new) {
                        $changes[] = sprintf($message, $old, $new);
                    }
                    break;

                case 'spec_id':
                    $old = $oldData[$field];
                    $new = $newData[$field];
                    if ($old !== $new) {
                        $old       = currentFromResultSetInterface($this->specTable->select(['id' => (int) $old]));
                        $new       = currentFromResultSetInterface($this->specTable->select(['id' => (int) $new]));
                        $changes[] = sprintf(
                            $message,
                            $old ? $old['short_name'] : '-',
                            $new ? $new['short_name'] : '-'
                        );
                    }
                    break;
            }
        }

        return $changes;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function getLogoAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $item = $this->itemModel->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        if (! $item['logo_id']) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->logoHydrator->extract([
            'image' => $item['logo_id'],
        ]));
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function postLogoAction()
    {
        if (! $this->user()->isAllowed('brand', 'logo')) {
            return $this->forbiddenAction();
        }

        $item = $this->itemModel->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        /** @var Request $request */
        $request = $this->getRequest();

        $data = array_merge(
            $this->params()->fromPost(),
            $request->getFiles()->toArray() // @phan-suppress-current-line PhanUndeclaredMethod
        );

        $this->itemLogoPutFilter->setData($data);

        if (! $this->itemLogoPutFilter->isValid()) {
            return $this->inputFilterResponse($this->itemLogoPutFilter);
        }

        $values = $this->itemLogoPutFilter->getValues();

        $oldImageId = $item['logo_id'];

        $imageId = $this->imageStorage->addImageFromFile($values['file']['tmp_name'], 'brand', [
            's3' => true,
        ]);

        $this->itemModel->getTable()->update([
            'logo_id' => $imageId,
        ], [
            'id' => $item['id'],
        ]);

        if ($oldImageId) {
            $this->imageStorage->removeImage($oldImageId);
        }

        $this->log(sprintf(
            'Закачен логотип %s',
            htmlspecialchars($item['name'])
        ), [
            'items' => $item['id'],
        ]);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }

    /**
     * @param array|ArrayAccess $car
     * @throws Exception
     */
    private function carTreeWalk($car, int $parentType = 0): array
    {
        $data = [
            'id'     => (int) $car['id'],
            'name'   => $this->car()->formatName($car, $this->language()),
            'childs' => [],
            'type'   => $parentType ? $parentType : null,
        ];

        $table = $this->itemParent->getTable();

        $select = new Sql\Select($table->getTable());
        $select
            ->columns(['item_id', 'type'])
            ->join('item', 'item_parent.item_id = item.id', [])
            ->where(['item_parent.parent_id' => $car['id']])
            ->order(array_merge(['item_parent.type'], $this->catalogue()->itemOrdering()));

        foreach ($table->selectWith($select) as $itemParentRow) {
            $carRow = $this->itemModel->getRow(['id' => $itemParentRow['item_id']]);
            if ($carRow) {
                $data['childs'][] = $this->carTreeWalk($carRow, (int) $itemParentRow['type']);
            }
        }

        return $data;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function treeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $item = $this->itemModel->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        return new JsonModel([
            'item' => $this->carTreeWalk($item),
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function refreshInheritanceAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forbiddenAction();
        }

        $item = $this->itemModel->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $this->itemModel->updateInteritance($item['id']);

        $this->specsService->updateActualValues($item['id']);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function specificationsAction()
    {
        $item = $this->itemModel->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $specs = $this->specsService->specifications([$item], [
            'language' => $this->language(),
        ]);

        $viewModel = new ViewModel([
            'specs' => $specs,
        ]);

        return $viewModel->setTerminal(true);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function childSpecificationsAction()
    {
        $item = $this->itemModel->getRow(['id' => (int) $this->params('id')]);
        if (! $item) {
            return $this->notFoundAction();
        }

        $childItems = $this->itemModel->getRows([
            'order'  => $this->catalogue()->itemOrdering(),
            'parent' => $item['id'],
        ]);

        $specs = $this->specsService->specifications($childItems, [
            'language' => $this->language(),
        ]);

        $viewModel = new ViewModel([
            'specs' => $specs,
        ]);

        return $viewModel->setTerminal(true);
    }

    public function vehicleTypeAction(): JsonModel
    {
        $brandId = (int) $this->params()->fromQuery('brand_id');

        $list = $this->vehicleType->getBrandVehicleTypes($brandId);

        return new JsonModel([
            'items' => $list,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function newItemsAction()
    {
        $category = $this->itemModel->getRow([
            'item_type_id' => Item::CATEGORY,
            'id'           => (int) $this->params('id'),
        ]);
        if (! $category) {
            return $this->notFoundAction();
        }

        $language = $this->language();

        $rows = $this->itemModel->getRows([
            'item_type_id' => [Item::VEHICLE, Item::ENGINE],
            'order'        => new Sql\Expression('MAX(ip1.timestamp) DESC'),
            'parent'       => [
                'item_type_id'     => [Item::CATEGORY, Item::FACTORY],
                'ancestor_or_self' => $category['id'],
                'linked_in_days'   => Categories::NEW_DAYS,
            ],
            'limit'        => 20,
        ]);

        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->itemModel->getNameData($row, $language);
        }

        $viewModel = new ViewModel([
            'items' => $items,
        ]);
        $viewModel->setTerminal(true);

        return $viewModel;
    }
}
