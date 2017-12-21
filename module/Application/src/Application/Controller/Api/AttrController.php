<?php

namespace Application\Controller\Api;

use Exception;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Paginator;
use Zend\View\Model\JsonModel;

use Autowp\User\Model\User;

use Application\Hydrator\Api\RestHydrator;
use Application\Model\Item;
use Application\Service\SpecificationsService;

class AttrController extends AbstractRestfulController
{
    /**
     * @var Item
     */
    private $item;

    /**
     * @var SpecificationsService
     */
    private $specsService = null;

    /**
     * @var User
     */
    private $userModel;

    /**
     * @var RestHydrator
     */
    private $conflictHydrator;

    /**
     * @var RestHydrator
     */
    private $userValueHydrator;

    /**
     * @var RestHydrator
     */
    private $valueHydrator;

    /**
     * @var InputFilter
     */
    private $conflictListInputFilter;

    /**
     * @var InputFilter
     */
    private $userValueListInputFilter;

    /**
     * @var TableGateway
     */
    private $userValueTable;

    /**
     * @var InputFilter
     */
    private $userValuePatchQueryFilter;

    /**
     * @var InputFilter
     */
    private $userValuePatchDataFilter;

    /**
     * @var InputFilter
     */
    private $attributeListInputFilter;

    /**
     * @var InputFilter
     */
    private $attributeHydrator;

    /**
     * @var InputFilter
     */
    private $valueListInputFilter;

    public function __construct(
        Item $item,
        SpecificationsService $specsService,
        User $userModel,
        RestHydrator $conflictHydrator,
        RestHydrator $userValueHydrator,
        RestHydrator $attributeHydrator,
        RestHydrator $valueHydrator,
        InputFilter $conflictListInputFilter,
        InputFilter $userValueListInputFilter,
        InputFilter $userValuePatchQueryFilter,
        InputFilter $userValuePatchDataFilter,
        InputFilter $attributeListInputFilter,
        InputFilter $valueListInputFilter
    ) {
        $this->item = $item;
        $this->specsService = $specsService;
        $this->userModel = $userModel;
        $this->conflictHydrator = $conflictHydrator;
        $this->conflictListInputFilter = $conflictListInputFilter;
        $this->userValueTable = $specsService->getUserValueTable();
        $this->userValueHydrator = $userValueHydrator;
        $this->attributeHydrator = $attributeHydrator;
        $this->valueHydrator = $valueHydrator;
        $this->userValueListInputFilter = $userValueListInputFilter;
        $this->userValuePatchQueryFilter = $userValuePatchQueryFilter;
        $this->userValuePatchDataFilter = $userValuePatchDataFilter;
        $this->attributeListInputFilter = $attributeListInputFilter;
        $this->valueListInputFilter = $valueListInputFilter;
    }

    public function conflictIndexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        $this->conflictListInputFilter->setData($this->params()->fromQuery());

        if (! $this->conflictListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->conflictListInputFilter);
        }

        $values = $this->conflictListInputFilter->getValues();

        $data = $this->specsService->getConflicts($user['id'], $values['filter'], (int)$values['page'], 30);

        $this->conflictHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($data['conflicts'] as $conflict) {
            $items[] = $this->conflictHydrator->extract($conflict);
        }

        return new JsonModel([
            'items'     => $items,
            'paginator' => $data['paginator']->getPages()
        ]);
    }

    public function userValueIndexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $this->userValueListInputFilter->setData($this->params()->fromQuery());

        if (! $this->userValueListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->userValueListInputFilter);
        }

        $values = $this->userValueListInputFilter->getValues();

        $select = new Sql\Select($this->userValueTable->getTable());

        $select->order('update_date DESC');

        $userId = (int)$values['user_id'];
        $itemId = (int)$values['item_id'];

        if (! $userId && ! $itemId) {
            return $this->forbiddenAction();
        }

        if ($userId) {
            $select->where(['user_id' => $userId]);
        }

        if ($itemId) {
            $select->where(['item_id' => $itemId]);
        }

        if ($values['exclude_user_id']) {
            $select->where(['user_id <> ?' => $values['exclude_user_id']]);
        }

        if ($values['zone_id']) {
            $select
                ->join(
                    'attrs_zone_attributes',
                    'attrs_values.attribute_id = attrs_zone_attributes.attribute_id',
                    []
                )
                ->where(['attrs_zone_attributes.zone_id' => $values['zone_id']]);
        }

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->userValueTable->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(30)
            ->setPageRange(20)
            ->setCurrentPageNumber($values['page']);

        $this->userValueHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language()
        ]);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->userValueHydrator->extract($row);
        }


        return new JsonModel([
            'paginator' => $paginator->getPages(),
            'items'     => $items
        ]);
    }

    public function userValueItemDeleteAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forbiddenAction();
        }

        $attributeId = (int)$this->params('attribute_id');
        $itemId = (int)$this->params('item_id');
        $userId = (int)$this->params('user_id');

        $this->specsService->deleteUserValue($attributeId, $itemId, $userId);

        return $this->getResponse()->setStatusCode(204);
    }

    public function userValuePatchAction()
    {
        if (! $this->user()->isAllowed('specifications', 'admin')) {
            return $this->forward('forbidden', 'error');
        }

        $this->userValuePatchQueryFilter->setData($this->params()->fromQuery());

        if (! $this->userValuePatchQueryFilter->isValid()) {
            return $this->inputFilterResponse($this->userValuePatchQueryFilter);
        }

        $query = $this->userValuePatchQueryFilter->getValues();


        $this->userValuePatchDataFilter->setData($this->processBodyContent($this->getRequest()));

        if (! $this->userValuePatchDataFilter->isValid()) {
            return $this->inputFilterResponse($this->userValuePatchDataFilter);
        }

        $data = $this->userValuePatchDataFilter->getValues();

        $srcItemId = (int)$query['item_id'];

        $eUserValueRows = $this->userValueTable->select([
            'item_id' => $srcItemId
        ]);

        $dstItemId = (int)$data['item_id'];

        foreach ($eUserValueRows as $eUserValueRow) {
            if ($dstItemId) {
                $srcPrimaryKey = [
                    'item_id'      => $eUserValueRow['item_id'],
                    'attribute_id' => $eUserValueRow['attribute_id'],
                    'user_id'      => $eUserValueRow['user_id']
                ];
                $dstPrimaryKey = [
                    'item_id'      => $dstItemId,
                    'attribute_id' => $eUserValueRow['attribute_id'],
                    'user_id'      => $eUserValueRow['user_id']
                ];
                $set = [
                    'item_id' => $dstItemId
                ];

                $cUserValueRow = $this->userValueTable->select($dstPrimaryKey)->current();

                if ($cUserValueRow) {
                    $rowId = implode('/', [$dstItemId, $eUserValueRow['attribute_id'], $eUserValueRow['user_id']]);
                    throw new Exception("Value row $rowId already exists");
                }

                $attrRow = $this->specsService->getAttributeTable()->select([
                    'id' => $eUserValueRow['attribute_id']
                ])->current();

                if (! $attrRow) {
                    throw new Exception("Attr not found");
                }

                $dataTable = $this->specsService->getUserValueDataTable($attrRow['type_id']);

                $eDataRows = [];
                foreach ($dataTable->select($srcPrimaryKey) as $row) {
                    $eDataRows[] = $row;
                }

                foreach ($eDataRows as $eDataRow) {
                    // check for data row existance
                    $filter = $dstPrimaryKey;
                    if ($attrRow['multiple']) {
                        $filter['ordering'] = $eDataRow['ordering'];
                    }
                    $cDataRow = $dataTable->select($filter)->current();

                    if ($cDataRow) {
                        throw new Exception("Data row already exists");
                    }
                }

                $this->userValueTable->update($set, $srcPrimaryKey);

                foreach ($eDataRows as $eDataRow) {
                    $filter = $srcPrimaryKey;
                    if ($attrRow['multiple']) {
                        $filter['ordering'] = $eDataRow['ordering'];
                    }

                    $dataTable->update($set, $filter);
                }
            }

            if ($dstItemId) {
                $this->specsService->updateActualValues($dstItemId);
                if ($srcItemId) {
                    $this->specsService->updateActualValues($eUserValueRow['item_id']);
                }
            }
        }

        return $this->getResponse()->setStatusCode(200);
    }

    public function attributeIndexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $this->attributeListInputFilter->setData($this->params()->fromQuery());

        if (! $this->attributeListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->attributeListInputFilter);
        }

        $values = $this->attributeListInputFilter->getValues();

        $attributes = $this->specsService->getAttributes([
            'parent'    => 0,
            'zone'      => $values['zone_id'],
            'recursive' => true
        ]);

        $this->attributeHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($attributes as $row) {
            $items[] = $this->attributeHydrator->extract($row);
        }

        return new JsonModel([
            'items'     => $items
        ]);
    }

    public function valueIndexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        if (! $this->user()->isAllowed('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $this->valueListInputFilter->setData($this->params()->fromQuery());

        if (! $this->valueListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->valueListInputFilter);
        }

        $values = $this->valueListInputFilter->getValues();

        $select = $this->specsService->getValueTable()->getSql()->select();

        $select->order('update_date DESC');

        $itemId = (int)$values['item_id'];

        if (! $itemId) {
            return $this->forbiddenAction();
        }

        if ($itemId) {
            $select->where(['item_id' => $itemId]);
        }

        if ($values['zone_id']) {
            $select
                ->join(
                    'attrs_zone_attributes',
                    'attrs_values.attribute_id = attrs_zone_attributes.attribute_id',
                    []
                )
                ->where(['attrs_zone_attributes.zone_id' => $values['zone_id']]);
        }

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->userValueTable->getAdapter())
        );

        $paginator
            ->setItemCountPerPage($values['limit'])
            ->setPageRange(20)
            ->setCurrentPageNumber($values['page']);

        $this->valueHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
        ]);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->valueHydrator->extract($row);
        }


        return new JsonModel([
            'paginator' => $paginator->getPages(),
            'items'     => $items
        ]);
    }
}
