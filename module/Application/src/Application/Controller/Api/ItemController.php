<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use geoPHP;
use Point;

use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;
use Autowp\User\Model\DbTable\User;
use Autowp\ZFComponents\Filter\FilenameSafe;
use Autowp\ZFComponents\Filter\SingleSpaces;

use Application\Hydrator\Api\RestHydrator;
use Application\ItemNameFormatter;
use Application\Model\Brand as BrandModel;
use Application\Model\DbTable;
use Application\Service\SpecificationsService;

use Zend_Db_Expr;

class ItemController extends AbstractRestfulController
{
    /**
     * @var User
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var ItemNameFormatter
     */
    private $itemNameFormatter;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    /**
     * @var InputFilter
     */
    private $itemInputFilter;

    /**
     * @var SpecificationsService
     */
    private $specificationsService;

    public function __construct(
        RestHydrator $hydrator,
        ItemNameFormatter $itemNameFormatter,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        SpecificationsService $specificationsService
    ) {
        $this->hydrator = $hydrator;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->listInputFilter = $listInputFilter;
        $this->itemInputFilter = $itemInputFilter;
        $this->specificationsService = $specificationsService;

        $this->table = new DbTable\Item();
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $select = $this->table->getAdapter()->select()
            ->from('item');

        $group = false;

        if ($data['last_item']) {
            $namespace = new \Zend\Session\Container('Moder_Car');
            if (isset($namespace->lastCarId)) {
                $select->where('item.id = ?', (int)$namespace->lastCarId);
            } else {
                $select->where(new Zend_Db_Expr('0'));
            }
        }

        switch ($data['order']) {
            case 'id_asc':
                $select->order('item.id ASC');
                break;
            case 'id_desc':
                $select->order('item.id DESC');
                break;
            case 'childs_count':
                $group = true;
                $select
                    ->columns(['childs_count' => new Zend_Db_Expr('count(item_parent.item_id)')])
                    ->join('item_parent', 'item_parent.parent_id = item.id', null)
                    ->order('childs_count desc');
                break;
            default:
                $select->order([
                    'item.name',
                    'item.body',
                    'item.spec_id',
                    'item.begin_order_cache',
                    'item.end_order_cache'
                ]);
                break;
        }

        if ($data['name']) {
            $select
                ->join('item_language', 'item.id = item_language.item_id', null)
                ->where('item_language.name like ?', $data['name']);
        }

        if ($data['name_exclude']) {
            $select
                ->join('item_language', 'item.id = item_language.item_id', null)
                ->where('item_language.name not like ?', $data['name_exclude']);
        }

        $id = (int)$this->params()->fromQuery('id');
        if ($id) {
            $select->where('item.id = ?', $id);
        }

        if ($data['descendant']) {
            $group = true;
            $select->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', null)
                ->where('item_parent_cache.item_id = ?', $data['descendant']);
        }

        if ($data['type_id']) {
            $select->where('item.item_type_id = ?', $data['type_id']);
        }

        if ($data['vehicle_type_id']) {
            if ($data['vehicle_type_id'] == 'empty') {
                $select
                    ->joinLeft('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', null)
                    ->where('vehicle_vehicle_type.vehicle_id is null');
            } else {
                $select
                    ->join('vehicle_vehicle_type', 'item.id = vehicle_vehicle_type.vehicle_id', null)
                    ->where('vehicle_vehicle_type.vehicle_type_id = ?', $data['vehicle_type_id']);
            }
        }

        if ($data['vehicle_childs_type_id']) {
            $group = true;
            $select
                ->join(
                    ['cpc_childs' => 'item_parent_cache'],
                    'item.id = cpc_childs.parent_id',
                    null
                )
                ->join(
                    ['vvt_child' => 'vehicle_vehicle_type'],
                    'cpc_childs.item_id = vvt_child.vehicle_id',
                    null
                )
                ->join('car_types_parents', 'vvt_child.vehicle_type_id = car_types_parents.id', null)
                ->where('car_types_parents.parent_id = ?', $data['vehicle_childs_type_id']);
        }

        if ($data['spec']) {
            $select->where('item.spec_id = ?', $data['spec']);
        }

        if ($data['parent_id']) {
            $select
                ->join('item_parent_cache', 'item.id = item_parent_cache.item_id', null)
                ->where('item_parent_cache.parent_id = ?', $data['parent_id'])
                ->where('item_parent_cache.item_id <> item_parent_cache.parent_id');
        }

        if ($data['from_year']) {
            $select->where('item.begin_year = ?', $data['from_year']);
        }

        if ($data['to_year']) {
            $select->where('item.end_year = ?', $data['to_year']);
        }

        if ($data['no_parent']) {
            $select
                ->joinLeft(
                    ['np_ip' => 'item_parent'],
                    'item.id = np_ip.item_id',
                    null
                )
                ->where('np_ip.item_id IS NULL');
        }

        if ($data['text']) {
            $select
                ->join('item_language', 'item.id = item_language.item_id', null)
                ->join('textstorage_text', 'item_language.text_id = textstorage_text.id', null)
                ->where('textstorage_text.text like ?', '%' . $data['text'] . '%');
        }

        if ($group) {
            $select->group('item.id');
        }

        $paginator = new \Zend\Paginator\Paginator(
            new Zend1DbSelect($select)
        );

        $limit = $data['limit'] ? $data['limit'] : 1;

        $paginator
            ->setItemCountPerPage($limit)
            ->setCurrentPageNumber($data['page']);

        $select->limitPage($paginator->getCurrentPageNumber(), $paginator->getItemCountPerPage());

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($this->table->getAdapter()->fetchAll($select) as $row) {
            $items[] = $this->hydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => get_object_vars($paginator->getPages()),
            'items'     => $items
        ]);
    }

    public function alphaAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();
        $carAdapter = $itemTable->getAdapter();
        $chars = $carAdapter->fetchCol(
            $carAdapter->select()
                ->distinct()
                ->from('item', ['char' => new Zend_Db_Expr('UPPER(LEFT(name, 1))')])
                ->order('char')
        );

        $groups = [
            'numbers' => [],
            'english' => [],
            'other'   => []
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
            'groups' => $groups
        ]);
    }

    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $select = $this->table->getAdapter()->select()
            ->from('item')
            ->where('id = ?', (int)$this->params('id'));

        $row = $this->table->getAdapter()->fetchRow($select);

        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields'],
            'user_id'  => $user ? $user['id'] : null
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }

    /**
     * @param int $itemTypeId
     * @return \Zend\InputFilter\InputFilterInterface
     */
    private function getPostInputFilter($itemTypeId)
    {
        $specTable = new DbTable\Spec();
        $db = $specTable->getAdapter();
        $specOptions = $db->fetchCol(
            $db->select()
                ->from($specTable->info('name'), 'id')
        );

        $spec = [
            'name' => [
                'required' => true,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 2,
                            'max' => DbTable\Item::MAX_NAME
                        ]
                    ]
                ]
            ],
            'full_name' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'max' => BrandModel::MAX_FULLNAME
                        ]
                    ]
                ]
            ],
            'catname' => [
                'required' => true,
                'filters'  => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class],
                    ['name' => FilenameSafe::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 3,
                            'max' => 100
                        ]
                    ],
                    [
                        'name'    => \Application\Validator\Item\CatnameNotExists::class,
                        'options' => [
                            'exclude' => null
                        ]
                    ]
                ]
            ],
            'body' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim'],
                    ['name' => SingleSpaces::class]
                ],
                'validators' => [
                    [
                        'name' => 'StringLength',
                        'options' => [
                            'min' => 1,
                            'max' => 20
                        ]
                    ]
                ]
            ],
            'spec_id' => [
                'required' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => $specOptions
                        ]
                    ]
                ]
            ],
            'spec_inherited' => [
                'required' => false
            ],
            'begin_model_year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'end_model_year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'begin_year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'begin_month' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'end_year' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'end_month' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'today' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits'],
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => ['', '0', '1']
                        ]
                    ]
                ]
            ],
            'is_concept' => [
                'required' => false
            ],
            'is_concept_inherited' => [
                'required' => false
            ],
            'is_group' => [
                'required' => false
            ],

            'lat' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ]
            ],
            'lng' => [
                'required' => false,
                'filters' => [
                    ['name' => 'StringTrim']
                ]
            ],
            'produced_count' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'produced_exactly' => [
                'required' => false
            ],
        ];

        $pointFields = in_array($itemTypeId, [
            DbTable\Item\Type::FACTORY,
            DbTable\Item\Type::MUSEUM
        ]);
        if (! $pointFields) {
            unset($spec['lat'], $spec['lng']);
        }

        if ($itemTypeId != DbTable\Item\Type::BRAND) {
            unset($spec['full_name']);
        }

        if (! in_array($itemTypeId, [DbTable\Item\Type::CATEGORY, DbTable\Item\Type::BRAND])) {
            unset($spec['catname']);
        }

        if (! in_array($itemTypeId, [DbTable\Item\Type::VEHICLE, DbTable\Item\Type::ENGINE])) {
            unset($spec['is_group']);
            unset($spec['is_concept']);
            unset($spec['produced_count'], $spec['produced_exactly']);
            unset($spec['begin_model_year'], $spec['end_model_year']);
            unset($spec['spec_id']);
            unset($spec['body']);
        }

        $factory = new \Zend\InputFilter\Factory();
        return $factory->createInputFilter($spec);
    }

    public function postAction()
    {
        if (! $this->user()->isAllowed('car', 'add')) {
            return $this->forbiddenAction();
        }

        $user = $this->user()->get();

        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        if (! isset($data['item_type_id'])) {
            return new ApiProblemResponse(new ApiProblem(400, 'Invalid item_type_id'));
        }

        $itemTypeId = (int)$data['item_type_id'];

        $inputFilter = $this->getPostInputFilter($itemTypeId);

        $fields = ['name'];
        switch ($itemTypeId) {
            case DbTable\Item\Type::CATEGORY:
            case DbTable\Item\Type::BRAND:
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

        $itemTable = $this->catalogue()->getItemTable();
        $item = $itemTable->createRow([
            'item_type_id'       => $itemTypeId,
            'body'               => '',
            'produced_exactly'   => 0,
            'is_concept_inherit' => 1,
            'spec_inherit'       => 1,
            'is_concept'         => 0
        ]);

        if (array_key_exists('name', $values)) {
            $item['name'] = $values['name'];
        }

        if (array_key_exists('full_name', $values)) {
            $item['full_name'] = $values['full_name'] ? $values['full_name'] : null;
        }

        if (array_key_exists('body', $values)) {
            $item['body'] = (string)$values['body'];
        }

        if (array_key_exists('begin_year', $values)) {
            $item['begin_year'] = $values['begin_year'] ? $values['begin_year'] : null;
        }

        if (array_key_exists('begin_month', $values)) {
            $item['begin_month'] = $values['begin_month'] ? $values['begin_month'] : null;
        }

        if (array_key_exists('end_year', $values)) {
            $endYear = $values['end_year'] ? $values['end_year'] : null;
            $item['end_year'] = $endYear;

            if ($endYear) {
                if ($endYear < date('Y')) {
                    $values['today'] = 0;
                }
            }
        }

        if (array_key_exists('end_month', $values)) {
            $item['end_month'] = $values['end_month'] ? $values['end_month'] : null;
        }

        if (array_key_exists('today', $values)) {
            if (strlen($values['today'])) {
                $item['today'] = $values['today'] ? 1 : 0;
            } else {
                $item['today'] = null;
            }
        }

        if (array_key_exists('begin_model_year', $values)) {
            $item['begin_model_year'] = $values['begin_model_year'] ? $values['begin_model_year'] : null;
        }

        if (array_key_exists('end_model_year', $values)) {
            $item['end_model_year'] = $values['end_model_year'] ? $values['end_model_year'] : null;
        }

        if (array_key_exists('is_concept', $values)) {
            $item['is_concept'] = $values['is_concept'] ? 1 : 0;
        }

        if (array_key_exists('is_concept_inherited', $values)) {
            $item['is_concept_inherit'] = $values['is_concept_inherited'] ? 1 : 0;
        }

        if (array_key_exists('catname', $values)) {
            if (! $values['catname']) {
                $filter = new \Autowp\ZFComponents\Filter\FilenameSafe();
                $values['catname'] = $filter->filter($values['name']);
            }

            $item['catname'] = $values['catname'];
        }

        if (array_key_exists('produced_count', $values)) {
            $item['produced'] = strlen($values['produced_count']) ? (int)$values['produced_count'] : null;
        }

        if (array_key_exists('produced_exactly', $values)) {
            $item['produced_exactly'] = $values['produced_exactly'] ? 1 : 0;
        }

        switch ($itemTypeId) {
            case DbTable\Item\Type::VEHICLE:
            case DbTable\Item\Type::ENGINE:
                if (array_key_exists('is_group', $values)) {
                    $item['is_group'] = $values['is_group'] ? 1 : 0;
                }
                break;
            case DbTable\Item\Type::CATEGORY:
            case DbTable\Item\Type::TWINS:
            case DbTable\Item\Type::BRAND:
            case DbTable\Item\Type::FACTORY:
            case DbTable\Item\Type::MUSEUM:
                $item['is_group'] = 1;
                break;
            default:
                return $this->notFoundAction();
        }

        if (array_key_exists('spec_inherited', $values)) {
            $item['spec_inherit'] = $values['spec_inherited'] ? 1 : 0;
        }

        if (array_key_exists('spec_id', $values)) {
            $item['spec_id'] = $values['spec_id'] ? $values['spec_id'] : null;
        }

        $item->save();

        if (array_key_exists('lat', $values) && array_key_exists('lng', $values)) {
            $point = null;
            if (strlen($values['lat']) && strlen($values['lng'])) {
                geoPHP::version(); // for autoload classes
                $point = new Point($values['lng'], $values['lat']);
            }
            $this->setItemPoint($item, $point);
        }

        if (array_key_exists('name', $values)) {
            $this->setLanguageName($item['id'], 'xx', $values['name']);
        }

        /*$vehicleType = new VehicleType();
        $vehicleType->setVehicleTypes($item->id, (array)$values['vehicle_type_id']);*/

        $item->updateOrderCache();

        $cpcTable = new DbTable\Item\ParentCache();
        $cpcTable->rebuildCache($item);

        /*$vehicleType = new VehicleType();
        $vehicleType->refreshInheritanceFromParents($item->id);*/

        $namespace = new \Zend\Session\Container('Moder_Car');
        $namespace->lastCarId = $item->id;

        $this->log(sprintf(
            'Создан новый автомобиль %s',
            htmlspecialchars($this->car()->formatName($item, 'en'))
        ), $item);

        $ucsTable = new DbTable\User\ItemSubscribe();
        $ucsTable->subscribe($user, $item);

        $itemTable->updateInteritance($item);

        $this->specificationsService->updateInheritedValues($item->id);

        $url = $this->url()->fromRoute('api/item/item/get', [
            'id' => $item->id
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        return $this->getResponse()->setStatusCode(201);
    }

    private function setItemPoint(DbTable\Item\Row $item, $point)
    {
        $itemPointTable = new DbTable\Item\Point();
        $itemPointRow = $itemPointTable->fetchRow([
            'item_id = ?' => $item['id']
        ]);

        if ($point) {
            if (! $itemPointRow) {
                $itemPointRow = $itemPointTable->createRow([
                    'item_id' => $item['id']
                ]);
            }

            $db = $itemPointTable->getAdapter();
            $itemPointRow->point = new Zend_Db_Expr($db->quoteInto('GeomFromText(?)', $point->out('wkt')));
            $itemPointRow->save();
        } else {
            if ($itemPointRow) {
                $itemPointRow->delete();
            }
        }
    }

    private function setLanguageName($carId, $language, $name)
    {
        $carLangTable = new DbTable\Item\Language();

        $carLangRow = $carLangTable->fetchRow([
            'item_id = ?'  => $carId,
            'language = ?' => $language
        ]);

        if (! $carLangRow) {
            $carLangRow = $carLangTable->createRow([
                'item_id'  => $carId,
                'language' => $language
            ]);
        }
        $carLangRow['name'] = $name;
        $carLangRow->save();
    }
}
