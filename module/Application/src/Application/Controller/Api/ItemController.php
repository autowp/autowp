<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Hydrator\Strategy\StrategyInterface;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\ApiProblemResponse;

use geoPHP;
use Point;

use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;
use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;
use Autowp\ZFComponents\Filter\FilenameSafe;
use Autowp\ZFComponents\Filter\SingleSpaces;

use Application\HostManager;
use Application\Hydrator\Api\RestHydrator;
use Application\Hydrator\Api\Strategy\Image;
use Application\ItemNameFormatter;
use Application\Model\Brand as BrandModel;
use Application\Model\BrandVehicle;
use Application\Model\DbTable;
use Application\Service\SpecificationsService;

use Zend_Db_Expr;

class ItemController extends AbstractRestfulController
{
    /**
     * @var DbTable\Item
     */
    private $table;

    /**
     * @var RestHydrator
     */
    private $hydrator;

    /**
     * @var StrategyInterface
     */
    private $logoHydrator;

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

    /**
     * @var DbTable\Item\ParentTable
     */
    private $itemParentTable;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var MessageService
     */
    private $message;

    public function __construct(
        RestHydrator $hydrator,
        Image $logoHydrator,
        ItemNameFormatter $itemNameFormatter,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        SpecificationsService $specificationsService,
        BrandVehicle $brandVehicle,
        HostManager $hostManager,
        MessageService $message
    ) {
        $this->hydrator = $hydrator;
        $this->logoHydrator = $logoHydrator;
        $this->itemNameFormatter = $itemNameFormatter;
        $this->listInputFilter = $listInputFilter;
        $this->itemInputFilter = $itemInputFilter;
        $this->specificationsService = $specificationsService;
        $this->brandVehicle = $brandVehicle;
        $this->hostManager = $hostManager;
        $this->message = $message;

        $this->table = new DbTable\Item();
        $this->itemParentTable = new DbTable\Item\ParentTable();
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

            $group = true;
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

        if ($data['ancestor_id']) {
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

        if ($data['parent_id']) {
            $select
                ->join('item_parent', 'item.id = item_parent.item_id', null)
                ->where('item_parent.parent_id = ?', $data['parent_id']);
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

        if ($data['suggestions_to']) {
            $db = $this->table->getAdapter();

            $select
                ->join(['ils' => 'item_language'], 'item.id = ils.item_id', [])
                ->join(['ils2' => 'item_language'], 'INSTR(ils.name, ils2.name)', [])
                ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                ->where('ils2.item_id = ?', $data['suggestions_to'])
                ->where(
                    'item.id NOT IN (?)',
                    $db->select()
                        ->from('item', ['id'])
                        ->where('item.item_type_id = ?', DbTable\Item\Type::BRAND)
                        ->join('item_parent_cache', 'item.id = item_parent_cache.parent_id', [])
                        ->where('item_parent_cache.item_id = ?', $data['suggestions_to'])
                );

            $group = true;
        }

        if ($data['have_childs_of_type']) {
            $select
                ->join(['ipc3' => 'item_parent_cache'], 'item.id = ipc3.parent_id', [])
                ->join(['child' => 'item'], 'ipc3.item_id = child.id', [])
                ->where('child.item_type_id = ?', (int)$data['have_childs_of_type']);

            $group = true;
        }

        if ($data['have_common_childs_with']) {
            $select
                ->join(['ipc1' => 'item_parent_cache'], 'ipc1.parent_id = item.id', null)
                ->join(['ipc2' => 'item_parent_cache'], 'ipc1.item_id = ipc2.item_id', null)
                ->where('ipc2.parent_id = ?', (int)$data['have_common_childs_with']);

            $group = true;
        }

        if ($data['have_childs_with_parent_of_type']) {
            $select
                ->join(['ipc4' => 'item_parent_cache'], 'item.id = ipc4.parent_id', [])
                ->join(['ip5' => 'item_parent'], 'ipc4.item_id = ip5.item_id', [])
                ->join(['child2' => 'item'], 'ip5.parent_id = child2.id', [])
                ->where('child2.item_type_id = ?', (int)$data['have_childs_with_parent_of_type']);

            $group = true;
        }

        if ($data['engine_id']) {
            $select->where('item.engine_item_id = ?', (int)$data['engine_id']);
        }

        if ($data['is_group']) {
            $select->where('item.is_group');
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
     * @param bool $post
     * @return \Zend\InputFilter\InputFilterInterface
     */
    private function getInputFilter($itemTypeId, $post, $itemId)
    {
        $specTable = new DbTable\Spec();
        $db = $specTable->getAdapter();
        $specOptions = $db->fetchCol(
            $db->select()
                ->from($specTable->info('name'), 'id')
        );
        $specOptions[] = 'inherited';

        $spec = [
            'name' => [
                'required' => $post,
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
                'required' => false,
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
                            'exclude' => $itemId
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
                'required'    => false,
                'allow_empty' => true
            ],
            'is_concept' => [
                'required'    => false,
                'allow_empty' => true
            ],
            'is_group' => [
                'required'    => false,
                'allow_empty' => true
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
            'produced' => [
                'required' => false,
                'filters'  => [
                    ['name' => 'StringTrim']
                ],
                'validators' => [
                    ['name' => 'Digits']
                ]
            ],
            'produced_exactly' => [
                'required'    => false,
                'allow_empty' => true
            ],
            'subscription' => [
                'required'    => false,
                'allow_empty' => true
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
            unset($spec['produced'], $spec['produced_exactly']);
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

        $inputFilter = $this->getInputFilter($itemTypeId, true, null);

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
            $item['is_concept_inherit'] = $values['is_concept'] == 'inherited' ? 1 : 0;
            if (! $item['is_concept_inherit']) {
                $item['is_concept'] = $values['is_concept'] ? 1 : 0;
            }
        }

        if (array_key_exists('catname', $values)) {
            if (! $values['catname'] || $values['catname'] == '_') {
                $filter = new \Autowp\ZFComponents\Filter\FilenameSafe();
                $values['catname'] = $filter->filter($values['name']);
            }

            $item['catname'] = $values['catname'];
        } else {
            $filter = new \Autowp\ZFComponents\Filter\FilenameSafe();
            $values['catname'] = $filter->filter($values['name']);
        }

        if (array_key_exists('produced', $values)) {
            $item['produced'] = strlen($values['produced']) ? (int)$values['produced'] : null;
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

        if (array_key_exists('spec_id', $values)) {
            if ($values['spec_id'] === 'inherited') {
                $item['spec_inherit'] = 1;
            } else {
                $item['spec_inherit'] = 0;
                $item['spec_id'] = $values['spec_id'] ? (int)$values['spec_id'] : null;
            }
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

    public function putAction()
    {
        if (! $this->user()->isAllowed('car', 'edit_meta')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $item = $itemTable->find($this->params('id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $request = $this->getRequest();
        $data = (array)$this->processBodyContent($request);

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

        $oldData = $item->toArray();

        $values = $inputFilter->getValues();

        if (array_key_exists('subscription', $values)) {
            $ucsTable = new DbTable\User\ItemSubscribe();
            if ($values['subscription']) {
                $ucsTable->subscribe($user, $item);
            } else {
                $ucsTable->unsubscribe($user, $item);
            }
        }

        if (array_key_exists('name', $values)) {
            $item['name'] = $values['name'];
            $this->setLanguageName($item['id'], 'xx', $values['name']);
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
                    $values['today'] = false;
                }
            }
        }

        if (array_key_exists('end_month', $values)) {
            $item['end_month'] = $values['end_month'] ? $values['end_month'] : null;
        }

        if (array_key_exists('today', $values)) {
            if (is_string($values['today'])) {
                $values['today'] = strlen($values['today']) ? (bool)strlen($values['today']) : null;
            }
            if ($values['today'] !== null) {
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
            $item['is_concept_inherit'] = $values['is_concept'] === 'inherited' ? 1 : 0;
            if (! $item['is_concept_inherit']) {
                $item['is_concept'] = $values['is_concept'] ? 1 : 0;
            }
        }

        if (array_key_exists('catname', $values)) {
            if (! $values['catname']) {
                $filter = new \Autowp\ZFComponents\Filter\FilenameSafe();
                $values['catname'] = $filter->filter($values['name']);
            }

            $item['catname'] = $values['catname'];
        }

        if (array_key_exists('produced', $values)) {
            $item['produced'] = strlen($values['produced']) ? (int)$values['produced'] : null;
        }

        if (array_key_exists('produced_exactly', $values)) {
            $item['produced_exactly'] = $values['produced_exactly'] ? 1 : 0;
        }

        switch ($item['item_type_id']) {
            case DbTable\Item\Type::VEHICLE:
            case DbTable\Item\Type::ENGINE:
                if (array_key_exists('is_group', $values)) {
                    $haveChilds = (bool)$this->itemParentTable->fetchRow([
                        'parent_id = ?' => $item['id']
                    ]);

                    if ($haveChilds) {
                        $item['is_group'] = 1;
                    } else {
                        $item['is_group'] = $values['is_group'] ? 1 : 0;
                    }
                }
                break;
            case DbTable\Item\Type::CATEGORY:
            case DbTable\Item\Type::TWINS:
            case DbTable\Item\Type::BRAND:
            case DbTable\Item\Type::FACTORY:
            case DbTable\Item\Type::MUSEUM:
                $item['is_group'] = 1;
                break;
        }

        if (array_key_exists('spec_id', $values)) {
            if ($values['spec_id'] === 'inherited') {
                $item['spec_inherit'] = 1;
            } else {
                $item['spec_inherit'] = 0;
                $item['spec_id'] = $values['spec_id'] ? (int)$values['spec_id'] : null;
            }
        }

        $item->save();

        if (isset($values['lat'], $values['lng'])) {
            $point = null;
            if (strlen($values['lat']) && strlen($values['lng'])) {
                geoPHP::version(); // for autoload classes
                $point = new Point($values['lng'], $values['lat']);
            }
            $this->setItemPoint($item, $point);
        }

        $itemTable->updateInteritance($item);
        $item->updateOrderCache();

        $this->brandVehicle->refreshAutoByVehicle($item->id);

        $ucsTable = new DbTable\User\ItemSubscribe();
        $ucsTable->subscribe($user, $item);

        $newData = $item->toArray();
        $htmlChanges = [];
        foreach ($this->buildChangesMessage($oldData, $newData, 'en') as $line) {
            $htmlChanges[] = htmlspecialchars($line);
        }

        $message = sprintf(
            'Редактирование мета-информации автомобиля %s',
            htmlspecialchars($this->car()->formatName($item, 'en')).
            ( count($htmlChanges) ? '<p>'.implode('<br />', $htmlChanges).'</p>' : '')
        );
        $this->log($message, $item);

        $user = $this->user()->get();
        foreach ($ucsTable->getItemSubscribers($item) as $subscriber) {
            if ($subscriber && ($subscriber->id != $user->id)) {
                $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                $changes = $this->buildChangesMessage($oldData, $newData, $subscriber->language);

                $message = sprintf(
                    $this->translate(
                        'pm/user-%s-edited-vehicle-meta-data-%s-%s-%s',
                        'default',
                        $subscriber->language
                    ),
                    $this->userModerUrl($user, true, $uri),
                    $this->car()->formatName($item, $subscriber->language),
                    $this->itemModerUrl($item, true, null, $uri),
                    ( count($changes) ? implode("\n", $changes) : '')
                );

                $this->message->send(null, $subscriber->id, $message);
            }
        }

        return $this->getResponse()->setStatusCode(200);
    }

    /**
     * @param \Autowp\User\Model\DbTable\User\Row $user
     * @param bool $full
     * @param \Zend\Uri\Uri $uri
     * @return string
     */
    private function userModerUrl(\Autowp\User\Model\DbTable\User\Row $user, $full = false, $uri = null)
    {
        return $this->url()->fromRoute('users/user', [
            'user_id' => $user->identity ? $user->identity : 'user' . $user->id
        ], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }

    /**
     * @param DbTable\Item\Row $car
     * @return string
     */
    private function itemModerUrl(DbTable\Item\Row $item, $full = false, $tab = null, $uri = null)
    {
        $url = 'moder/items/item/' . $item['id'];

        if ($tab) {
            $url .= '?' . http_build_query([
                'tab' => $tab
            ]);
        }

        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => $full,
            'uri'             => $uri
        ]) . $url;
    }

    private function buildChangesMessage($oldData, $newData, $language)
    {
        $fields = [
            'name'             => ['str', 'moder/vehicle/changes/name-%s-%s'],
            'body'             => ['str', 'moder/vehicle/changes/body-%s-%s'],
            'begin_year'       => ['int', 'moder/vehicle/changes/from/year-%s-%s'],
            'begin_month'      => ['int', 'moder/vehicle/changes/from/month-%s-%s'],
            'end_year'         => ['int', 'moder/vehicle/changes/to/year-%s-%s'],
            'end_month'        => ['int', 'moder/vehicle/changes/to/month-%s-%s'],
            'today'            => ['bool', 'moder/vehicle/changes/to/today-%s-%s'],
            'produced'         => ['int', 'moder/vehicle/changes/produced/count-%s-%s'],
            'produced_exactly' => ['bool', 'moder/vehicle/changes/produced/exactly-%s-%s'],
            'is_concept'       => ['bool', 'moder/vehicle/changes/is-concept-%s-%s'],
            'is_group'         => ['bool', 'moder/vehicle/changes/is-group-%s-%s'],
            'begin_model_year' => ['int', 'moder/vehicle/changes/model-years/from-%s-%s'],
            'end_model_year'   => ['int', 'moder/vehicle/changes/model-years/to-%s-%s'],
            'spec_id'          => ['spec_id', 'moder/vehicle/changes/spec-%s-%s'],
            //'vehicle_type_id'  => ['vehicle_type_id', 'moder/vehicle/changes/car-type-%s-%s']
        ];

        $changes = [];
        foreach ($fields as $field => $info) {
            $message = $this->translate($info[1], 'default', $language);
            switch ($info[0]) {
                case 'int':
                    $old = is_null($oldData[$field]) ? null : (int)$oldData[$field];
                    $new = is_null($newData[$field]) ? null : (int)$newData[$field];
                    if ($old !== $new) {
                        $changes[] = sprintf($message, $old, $new);
                    }
                    break;
                case 'str':
                    $old = is_null($oldData[$field]) ? null : (string)$oldData[$field];
                    $new = is_null($newData[$field]) ? null : (string)$newData[$field];
                    if ($old !== $new) {
                        $changes[] = sprintf($message, $old, $new);
                    }
                    break;
                case 'bool':
                    $old = is_null($oldData[$field])
                        ? null
                        : $this->translate($oldData[$field]
                            ? 'moder/vehicle/changes/boolean/true'
                            : 'moder/vehicle/changes/boolean/false');
                    $new = is_null($newData[$field])
                        ? null
                        : $this->translate($newData[$field]
                            ? 'moder/vehicle/changes/boolean/true'
                            : 'moder/vehicle/changes/boolean/false');
                    if ($old !== $new) {
                        $changes[] = sprintf($message, $old, $new);
                    }
                    break;

                case 'spec_id':
                    $specTable = new DbTable\Spec();
                    $old = $oldData[$field];
                    $new = $newData[$field];
                    if ($old !== $new) {
                        $old = $specTable->find($old)->current();
                        $new = $specTable->find($new)->current();
                        $changes[] = sprintf($message, $old ? $old->short_name : '-', $new ? $new->short_name : '-');
                    }
                    break;

                /*case 'vehicle_type_id':
                    $vehicleTypeTable = new DbTable\Vehicle\Type();
                    $old = $oldData[$field];
                    $new = $newData[$field];
                    $old = $old ? (array)$old : [];
                    $new = $new ? (array)$new : [];
                    if (array_diff($old, $new) !== array_diff($new, $old)) {
                        $oldNames = [];
                        foreach ($vehicleTypeTable->find($old) as $row) {
                            $oldNames[] = $this->translate($row->name);
                        }
                        $newNames = [];
                        foreach ($vehicleTypeTable->find($new) as $row) {
                            $newNames[] = $this->translate($row->name);
                        }
                        $changes[] = sprintf(
                            $message,
                            $oldNames ? implode(', ', $oldNames) : '-',
                            $newNames ? implode(', ', $newNames) : '-'
                            );
                    }
                    break;*/
            }
        }

        return $changes;
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

    public function getLogoAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $item = $itemTable->find($this->params('id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }

        if (! $item['logo_id']) {
            return $this->notFoundAction();
        }

        return new JsonModel($this->logoHydrator->extract([
            'image' => $item['logo_id']
        ]));
    }

    public function putLogoAction()
    {
        if (! $this->user()->isAllowed('brand', 'logo')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $item = $itemTable->find($this->params('id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }

        $user = $this->user()->get();

        $filePath = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($filePath, $this->getRequest()->getContent());

        $factory = new \Zend\InputFilter\Factory();
        $input = $factory->createInput([
            'required'   => true,
            'validators' => [
                ['name' => 'FileIsImage'],
                [
                    'name' => 'FileSize',
                    'break_chain_on_failure' => true,
                    'options' => [
                        'max' => 10 * 1024 * 1024
                    ]
                ],
                [
                    'name' => 'FileIsImage',
                    'break_chain_on_failure' => true,
                ],
                [
                    'name' => 'FileMimeType',
                    'break_chain_on_failure' => true,
                    'options' => [
                        'mimeType' => 'image/png'
                    ]
                ],
                [
                    'name' => 'FileImageSize',
                    'break_chain_on_failure' => true,
                    'options' => [
                        'minWidth'  => 50,
                        'minHeight' => 50
                    ]
                ],
            ]
        ]);

        $input->setValue($filePath);

        if (! $input->isValid()) {
            return $this->inputResponse($input);
        }

        $oldImageId = $item->logo_id;

        $imageId = $this->imageStorage()->addImageFromFile($filePath, 'brand');

        $item->logo_id = $imageId;
        $item->save();

        if ($oldImageId) {
            $imageStorage->removeImage($oldImageId);
        }

        $this->log(sprintf(
            'Закачен логотип %s',
            htmlspecialchars($item->name)
        ), $item);

        return $this->getResponse()->setStatusCode(200);
    }

    private function carTreeWalk(DbTable\Item\Row $car, $itemParentRow = null)
    {
        $data = [
            'id'     => (int)$car['id'],
            'name'   => $this->car()->formatName($car, $this->language()),
            'childs' => [],
            'type'   => $itemParentRow ? (int)$itemParentRow->type : null
        ];

        $itemParentRows = $this->itemParentTable->fetchAll(
            $this->itemParentTable->select(true)
                ->join('item', 'item_parent.item_id = item.id', null)
                ->where('item_parent.parent_id = ?', $car['id'])
                ->order(array_merge(['item_parent.type'], $this->catalogue()->itemOrdering()))
        );

        $itemTable = $this->catalogue()->getItemTable();
        foreach ($itemParentRows as $itemParentRow) {
            $carRow = $itemTable->find($itemParentRow->item_id)->current();
            if ($carRow) {
                $data['childs'][] = $this->carTreeWalk($carRow, $itemParentRow);
            }
        }

        return $data;
    }

    public function treeAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $itemTable = $this->catalogue()->getItemTable();

        $item = $itemTable->find($this->params('id'))->current();
        if (! $item) {
            return $this->notFoundAction();
        }

        return new JsonModel([
            'item' => $this->carTreeWalk($item)
        ]);
    }
}
