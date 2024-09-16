<?php

namespace Application\Controller\Api;

use Application\Hydrator\Api\AbstractRestHydrator;
use Application\Service\SpecificationsService;
use Autowp\User\Controller\Plugin\User as UserPlugin;
use Exception;
use Laminas\ApiTools\ApiProblem\ApiProblemResponse;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\Paginator;
use Laminas\Stdlib\ResponseInterface;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

use function Autowp\Commons\currentFromResultSetInterface;
use function implode;

/**
 * @method UserPlugin user($user = null)
 * @method ViewModel forbiddenAction()
 * @method string language()
 * @method ApiProblemResponse inputFilterResponse(InputFilterInterface $inputFilter)
 */
class AttrController extends AbstractRestfulController
{
    private SpecificationsService $specsService;

    private AbstractRestHydrator $conflictHydrator;

    private AbstractRestHydrator $userValueHydrator;

    private InputFilter $conflictListInputFilter;

    private InputFilter $userValueListInputFilter;

    private TableGateway $userValueTable;

    private InputFilter $userValuePatchQueryFilter;

    private InputFilter $userValuePatchDataFilter;

    public function __construct(
        SpecificationsService $specsService,
        AbstractRestHydrator $conflictHydrator,
        AbstractRestHydrator $userValueHydrator,
        InputFilter $conflictListInputFilter,
        InputFilter $userValueListInputFilter,
        InputFilter $userValuePatchQueryFilter,
        InputFilter $userValuePatchDataFilter
    ) {
        $this->specsService              = $specsService;
        $this->conflictHydrator          = $conflictHydrator;
        $this->conflictListInputFilter   = $conflictListInputFilter;
        $this->userValueTable            = $specsService->getUserValueTable();
        $this->userValueHydrator         = $userValueHydrator;
        $this->userValueListInputFilter  = $userValueListInputFilter;
        $this->userValuePatchQueryFilter = $userValuePatchQueryFilter;
        $this->userValuePatchDataFilter  = $userValuePatchDataFilter;
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
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

        $data = $this->specsService->getConflicts($user['id'], $values['filter'], (int) $values['page'], 30);

        $this->conflictHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user['id'],
        ]);

        $items = [];
        foreach ($data['conflicts'] as $conflict) {
            $items[] = $this->conflictHydrator->extract($conflict);
        }

        return new JsonModel([
            'items'     => $items,
            'paginator' => $data['paginator']->getPages(),
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     */
    public function userValueIndexAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        if (! $this->user()->enforce('specifications', 'edit')) {
            return $this->forbiddenAction();
        }

        $this->userValueListInputFilter->setData($this->params()->fromQuery());

        if (! $this->userValueListInputFilter->isValid()) {
            return $this->inputFilterResponse($this->userValueListInputFilter);
        }

        $values = $this->userValueListInputFilter->getValues();

        $select = new Sql\Select($this->userValueTable->getTable());

        $select->order('update_date DESC');

        $userId = (int) $values['user_id'];
        $itemId = (int) $values['item_id'];

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
                    'attrs_user_values.attribute_id = attrs_zone_attributes.attribute_id',
                    []
                )
                ->where(['attrs_zone_attributes.zone_id' => $values['zone_id']]);
        }

        /** @var Adapter $adapter */
        $adapter   = $this->userValueTable->getAdapter();
        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($select, $adapter)
        );

        $paginator
            ->setItemCountPerPage($values['limit'] ?: 30)
            ->setPageRange(20)
            ->setCurrentPageNumber($values['page']);

        $this->userValueHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
        ]);

        $items = [];
        foreach ($paginator->getCurrentItems() as $row) {
            $items[] = $this->userValueHydrator->extract($row);
        }

        return new JsonModel([
            'paginator' => $paginator->getPages(),
            'items'     => $items,
        ]);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function userValueItemDeleteAction()
    {
        if (! $this->user()->enforce('specifications', 'admin')) {
            return $this->forbiddenAction();
        }

        /** @psalm-suppress InvalidCast */
        $attributeId = (int) $this->params('attribute_id');
        /** @psalm-suppress InvalidCast */
        $itemId = (int) $this->params('item_id');
        /** @psalm-suppress InvalidCast */
        $userId = (int) $this->params('user_id');

        $this->specsService->deleteUserValue($attributeId, $itemId, $userId);

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_204);
    }

    /**
     * @return ViewModel|ResponseInterface|array
     * @throws Exception
     */
    public function userValuePatchAction()
    {
        $user = $this->user()->get();

        if (! $user) {
            return $this->forbiddenAction();
        }

        if (! $this->user()->enforce('specifications', 'edit')) {
            return $this->forbiddenAction();
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

        $srcItemId = (int) $query['item_id'];

        if ($srcItemId) {
            $eUserValueRows = $this->userValueTable->select([
                'item_id' => $srcItemId,
            ]);

            $dstItemId = (int) $data['item_id'];

            if ($dstItemId) {
                foreach ($eUserValueRows as $eUserValueRow) {
                    $srcPrimaryKey = [
                        'item_id'      => $eUserValueRow['item_id'],
                        'attribute_id' => $eUserValueRow['attribute_id'],
                        'user_id'      => $eUserValueRow['user_id'],
                    ];
                    $dstPrimaryKey = [
                        'item_id'      => $dstItemId,
                        'attribute_id' => $eUserValueRow['attribute_id'],
                        'user_id'      => $eUserValueRow['user_id'],
                    ];
                    $set           = [
                        'item_id' => $dstItemId,
                    ];

                    $cUserValueRow = currentFromResultSetInterface($this->userValueTable->select($dstPrimaryKey));

                    if ($cUserValueRow) {
                        $rowId = implode('/', [
                            $dstItemId,
                            $eUserValueRow['attribute_id'],
                            $eUserValueRow['user_id'],
                        ]);
                        throw new Exception("Value row $rowId already exists");
                    }

                    $attrRow = currentFromResultSetInterface($this->specsService->getAttributeTable()->select([
                        'id' => $eUserValueRow['attribute_id'],
                    ]));

                    if (! $attrRow) {
                        throw new Exception("Attr not found");
                    }

                    $dataTable = $this->specsService->getUserValueDataTable($attrRow['type_id']);

                    $eDataRows = [];
                    foreach ($dataTable->select($srcPrimaryKey) as $row) {
                        $eDataRows[] = $row;
                    }

                    foreach ($eDataRows as $eDataRow) {
                        // check for data row existence
                        $filter = $dstPrimaryKey;
                        if ($attrRow['multiple']) {
                            $filter['ordering'] = $eDataRow['ordering'];
                        }
                        $cDataRow = currentFromResultSetInterface($dataTable->select($filter));

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

                    $this->specsService->updateActualValues($dstItemId);
                    $this->specsService->updateActualValues($eUserValueRow['item_id']);
                }
            }
        }

        if ($data['items']) {
            foreach ($data['items'] as $item) {
                if ((int) $item['user_id'] !== (int) $user['id']) {
                    return $this->forbiddenAction();
                }

                $this->specsService->setUserValue2(
                    $item['user_id'],
                    $item['attribute_id'],
                    $item['item_id'],
                    $item['value'],
                    (bool) $item['empty']
                );
            }
        }

        /** @var Response $response */
        $response = $this->getResponse();
        return $response->setStatusCode(Response::STATUS_CODE_200);
    }
}
