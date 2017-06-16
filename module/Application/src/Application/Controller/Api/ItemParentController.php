<?php

namespace Application\Controller\Api;

use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;

use Autowp\Commons\Paginator\Adapter\Zend1DbSelect;
use Autowp\Message\MessageService;
use Autowp\User\Model\DbTable\User;

use Application\HostManager;
use Application\Hydrator\Api\RestHydrator;
use Application\Model\BrandVehicle;
use Application\Model\DbTable;
use Application\Model\VehicleType;
use Application\Service\SpecificationsService;

class ItemParentController extends AbstractRestfulController
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
     * @var BrandVehicle
     */
    private $brandVehicle;

    /**
     * @var SpecificationsService
     */
    private $specificationsService;

    /**
     * @var HostManager
     */
    private $hostManager;

    /**
     * @var MessageService
     */
    private $message;

    /**
     * @var InputFilter
     */
    private $listInputFilter;

    /**
     * @var InputFilter
     */
    private $itemInputFilter;

    /**
     * @var InputFilter
     */
    private $postInputFilter;

    public function __construct(
        RestHydrator $hydrator,
        InputFilter $listInputFilter,
        InputFilter $itemInputFilter,
        InputFilter $postInputFilter,
        BrandVehicle $brandVehicle,
        SpecificationsService $specificationsService,
        HostManager $hostManager,
        MessageService $message
    ) {
        $this->hydrator = $hydrator;
        $this->listInputFilter = $listInputFilter;
        $this->itemInputFilter = $itemInputFilter;
        $this->postInputFilter = $postInputFilter;

        $this->table = new DbTable\Item\ParentTable();

        $this->brandVehicle = $brandVehicle;
        $this->specificationsService = $specificationsService;
        $this->hostManager = $hostManager;
        $this->message = $message;
    }

    public function indexAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->listInputFilter->setData($this->params()->fromQuery());

        if (! $this->listInputFilter->isValid()) {
            return $this->inputFilterResponse($this->listInputFilter);
        }

        $data = $this->listInputFilter->getValues();

        $select = $this->table->getAdapter()->select()
            ->from($this->table->info('name'))
            ->join('item', 'item_parent.item_id = item.id', [])
            ->order([
                'item_parent.type',
                'item.name',
                'item.body',
                'item.spec_id',
                'item.begin_order_cache',
                'item.end_order_cache'
            ]);

        if ($data['ancestor_id']) {
            $select
                ->join('item_parent_cache', 'item_parent.item_id = item_parent_cache.item_id', [])
                ->where('item_parent_cache.parent_id = ?', $data['ancestor_id'])
                ->group(['item_parent.item_id']);
        }

        if ($data['item_type_id']) {
            $select->where('item.item_type_id = ?', $data['item_type_id']);
        }

        if ($data['concept']) {
            $select->where('item.is_concept');
        }

        if ($data['parent_id']) {
            $select->where('item_parent.parent_id = ?', $data['parent_id']);
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
            'fields'   => $data['fields']
            //'user_id'  => $user ? $user['id'] : null
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

    public function itemAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $this->itemInputFilter->setData($this->params()->fromQuery());

        if (! $this->itemInputFilter->isValid()) {
            return $this->inputFilterResponse($this->itemInputFilter);
        }

        $data = $this->itemInputFilter->getValues();

        $select = $this->table->getAdapter()->select()
            ->from($this->table->info('name'))
            ->where('item_id = ?', (int)$this->params('item_id'))
            ->where('parent_id = ?', (int)$this->params('parent_id'));

        $row = $this->table->getAdapter()->fetchRow($select);
        if (! $row) {
            return $this->notFoundAction();
        }

        $this->hydrator->setOptions([
            'language' => $this->language(),
            'fields'   => $data['fields']
            //'user_id'  => $user ? $user['id'] : null
        ]);

        return new JsonModel($this->hydrator->extract($row));
    }

    public function postAction()
    {
        $canMove = $this->user()->isAllowed('car', 'move');
        if (! $canMove) {
            return $this->forbiddenAction();
        }

        $request = $this->getRequest();
        if ($this->requestHasContentType($request, self::CONTENT_TYPE_JSON)) {
            $data = $this->jsonDecode($request->getContent());
        } else {
            $data = $request->getPost()->toArray();
        }

        $this->postInputFilter->setData($data);

        if (! $this->postInputFilter->isValid()) {
            return $this->inputFilterResponse($this->postInputFilter);
        }

        $data = $this->postInputFilter->getValues();

        $itemTable = $this->catalogue()->getItemTable();

        $item = $itemTable->find($data['item_id'])->current();
        if (! $item) {
            return $this->notFoundAction();
        }

        $parentItem = $itemTable->find($data['parent_id'])->current();
        if (! $parentItem) {
            return $this->notFoundAction();
        }

        $this->brandVehicle->create($parentItem->id, $item->id);

        $itemTable->updateInteritance($item);

        $vehicleType = new VehicleType();
        $vehicleType->refreshInheritanceFromParents($item->id);

        $this->specificationsService->updateActualValues($item->id);

        $message = sprintf(
            '%s выбран как родительский автомобиль для %s',
            htmlspecialchars($this->car()->formatName($parentItem, 'en')),
            htmlspecialchars($this->car()->formatName($item, 'en'))
        );
        $this->log($message, [$item, $parentItem]);

        $ucsTable = new DbTable\User\ItemSubscribe();
        $user = $this->user()->get();

        $subscribers = [];
        foreach ($ucsTable->getItemSubscribers($item) as $subscriber) {
            $subscribers[$subscriber->id] = $subscriber;
        }

        foreach ($ucsTable->getItemSubscribers($parentItem) as $subscriber) {
            $subscribers[$subscriber->id] = $subscriber;
        }

        foreach ($subscribers as $subscriber) {
            if ($subscriber->id != $user->id) {
                $uri = $this->hostManager->getUriByLanguage($subscriber->language);

                $message = sprintf(
                    $this->translate(
                        'pm/user-%s-adds-item-%s-%s-to-item-%s-%s',
                        'default',
                        $subscriber->language
                    ),
                    $this->userModerUrl($user, true, $uri),
                    $this->car()->formatName($item, $subscriber->language),
                    $this->carModerUrl($item, true, null, $uri),
                    $this->car()->formatName($parentItem, $subscriber->language),
                    $this->carModerUrl($parentItem, true, null, $uri)
                );

                $this->message->send(null, $subscriber->id, $message);
            }
        }

        $url = $this->url()->fromRoute('api/item-parent/item/get', [
            'parent_id' => $parentItem->id,
            'item_id'   => $item->id
        ]);
        $this->getResponse()->getHeaders()->addHeaderLine('Location', $url);

        return $this->getResponse()->setStatusCode(201);
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
    private function carModerUrl(DbTable\Item\Row $car, $full = false, $tab = null, $uri = null)
    {
        $params = [
            'action'  => 'car',
            'item_id' => $car->id,
        ];

        if ($tab) {
            $params['tab'] = $tab;
        }

        return $this->url()->fromRoute('moder/cars/params', $params, [
            'force_canonical' => $full,
            'uri'             => $uri
        ]);
    }
}
