<?php

namespace Application\Controller\Api;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Paginator;
use Zend\View\Model\JsonModel;

use Autowp\Commons\Db\Table\Row;
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

    public function __construct(
        Item $item,
        SpecificationsService $specsService,
        User $userModel,
        RestHydrator $conflictHydrator,
        RestHydrator $userValueHydrator,
        InputFilter $conflictListInputFilter,
        InputFilter $userValueListInputFilter
    ) {
        $this->item = $item;
        $this->specsService = $specsService;
        $this->userModel = $userModel;
        $this->conflictHydrator = $conflictHydrator;
        $this->conflictListInputFilter = $conflictListInputFilter;
        $this->userValueTable = $specsService->getUserValueTable();
        $this->userValueHydrator = $userValueHydrator;
        $this->userValueListInputFilter = $userValueListInputFilter;
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

        if ($userId = (int)$values['user_id']) {
            $select->where(['user_id' => $userId]);
        }

        $paginator = new Paginator\Paginator(
            new Paginator\Adapter\DbSelect($select, $this->userValueTable->getAdapter())
        );

        $paginator
            ->setItemCountPerPage(3)
            ->setPageRange(20)
            ->setCurrentPageNumber($values['page']);

        $this->userValueHydrator->setOptions([
            'fields'   => $values['fields'],
            'language' => $this->language(),
            'user_id'  => $user ? $user['id'] : null
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
}
