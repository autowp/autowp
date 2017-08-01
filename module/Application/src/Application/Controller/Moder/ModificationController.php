<?php

namespace Application\Controller\Moder;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;

use Application\Form\Modification as ModificationForm;
use Application\Model\Modification;
use Application\Model\Item;

class ModificationController extends AbstractActionController
{
    /**
     * @var TableGateway
     */
    private $combinationModificationTable;

    /**
     * @var TableGateway
     */
    private $modificationTable;

    /**
     * @var Modification
     */
    private $modification;

    /**
     * @var TableGateway
     */
    private $modificationGroupTable;

    /**
     * @var Item
     */
    private $item;

    public function __construct(
        TableGateway $combinationModificationTable,
        TableGateway $modificationTable,
        ModificationForm $modification,
        TableGateway $modificationGroupTable,
        Item $item
    ) {
        $this->combinationModificationTable = $combinationModificationTable;
        $this->modificationTable = $modificationTable;
        $this->modification = $modification;
        $this->modificationGroupTable = $modificationGroupTable;
        $this->item = $item;
    }

    private function carModerUrl($carId, $full = false, $tab = null)
    {
        $url = 'moder/items/item/' . $carId;

        if ($tab) {
            $url .= '?' . http_build_query([
                'tab' => $tab
            ]);
        }

        return $this->url()->fromRoute('ng', ['path' => ''], [
            'force_canonical' => $full
        ]) . $url;
    }

    private function redirectToCar($carId, $tab = null)
    {
        return $this->redirect($this->carModerUrl($carId, true, $tab));
    }

    public function addAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $car = $this->item->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $groupOptions = [
            '' => 'без группы'
        ];

        foreach ($this->modificationGroupTable->select([]) as $mgRow) {
            $groupOptions[$mgRow['id']] = $mgRow['name'];
        }

        $form = new ModificationForm([
            'groupOptions' => $groupOptions,
            'action'       => $this->_helper->url->url()
        ]);

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $endYear = (int)$values['end_year'];

            $today = null;
            if ($endYear) {
                if ($endYear < date('Y')) {
                    $today = 0;
                } else {
                    $today = null;
                }
            } else {
                switch ($values['today']) {
                    case 0:
                        $today = null;
                        break;

                    case 1:
                        $today = 0;
                        break;

                    case 2:
                        $today = 1;
                        break;
                }
            }

            $this->modificationTable->insert([
                'item_id'          => $car['id'],
                'name'             => $values['name'],
                'group_id'         => $values['group_id'] ? $values['group_id'] : null,
                'begin_year'       => $values['begin_year'] ? $values['begin_year'] : null,
                'end_year'         => $endYear ? $endYear : null,
                'begin_month'      => $values['begin_month'] ? $values['begin_month'] : null,
                'end_month'        => $values['end_month'] ? $values['end_month'] : null,
                'begin_model_year' => $values['begin_model_year'] ? $values['begin_model_year'] : null,
                'end_model_year'   => $values['end_model_year'] ? $values['end_model_year'] : null,
                'today'            => $today,
                'produced'         => $values['produced'],
                'produced_exactly' => $values['produced_exactly'] ? 1 : 0,
            ]);

            return $this->redirectToCar($car['id'], 'modifications');
        }

        $this->view->assign([
            'form'   => $form,
            'carUrl' => $this->carModerUrl($car['id'], false, 'modifications')
        ]);
    }

    public function editAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $car = $this->item->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $mRow = $this->modificationTable->select(['id' => (int)$this->getParam('modification_id')])->current();
        if (! $mRow) {
            return $this->notFoundAction();
        }

        $groupOptions = [
            '' => 'без группы'
        ];

        foreach ($this->modificationGroupTable->select([]) as $mgRow) {
            $groupOptions[$mgRow['id']] = $mgRow['name'];
        }

        $form = new ModificationForm([
            'groupOptions' => $groupOptions,
            'action'       => $this->_helper->url->url()
        ]);

        $form->populate([
            'name'             => $mRow['name'],
            'group_id'         => $mRow['group_id'],
            'begin_year'       => $mRow['begin_year'],
            'end_year'         => $mRow['end_year'],
            'begin_month'      => $mRow['begin_month'],
            'end_month'        => $mRow['end_month'],
            'begin_model_year' => $mRow['begin_model_year'],
            'end_model_year'   => $mRow['end_model_year'],
            'today'            => $mRow['today'] ? 1 : 0,
            'produced'         => $mRow['produced'],
            'produced_exactly' => $mRow['produced_exactly'],
        ]);

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {
            $values = $form->getValues();

            $endYear = (int)$values['end_year'];

            $today = null;
            if ($endYear) {
                if ($endYear < date('Y')) {
                    $today = 0;
                } else {
                    $today = null;
                }
            } else {
                switch ($values['today']) {
                    case 0:
                        $today = null;
                        break;

                    case 1:
                        $today = 0;
                        break;

                    case 2:
                        $today = 1;
                        break;
                }
            }

            $this->modificationTable->update([
                'name'             => $values['name'],
                'group_id'         => $values['group_id'] ? $values['group_id'] : null,
                'begin_year'       => $values['begin_year'] ? $values['begin_year'] : null,
                'end_year'         => $endYear ? $endYear : null,
                'begin_month'      => $values['begin_month'] ? $values['begin_month'] : null,
                'end_month'        => $values['end_month'] ? $values['end_month'] : null,
                'begin_model_year' => $values['begin_model_year'] ? $values['begin_model_year'] : null,
                'end_model_year'   => $values['end_model_year'] ? $values['end_model_year'] : null,
                'today'            => $today,
                'produced'         => $values['produced'],
                'produced_exactly' => $values['produced_exactly'] ? 1 : 0,
            ], [
                'id' => $mRow['id']
            ]);

            return $this->redirectToCar($car['id'], 'modifications');
        }

        $this->view->assign([
            'form'   => $form,
            'carUrl' => $this->carModerUrl($car['id'], false, 'modifications'),
        ]);
    }

    public function mapAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $car = $this->item->getRow(['id' => (int)$this->params('item_id')]);
        if (! $car) {
            return $this->notFoundAction();
        }

        $select = new Sql\Select($this->modificationGroupTable->getTable());

        $select->join('modification', 'modification_group.id = modification.group_id', [])
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
            ->where(['item_parent_cache.item_id' => $car['id']])
            ->group('modification_group.id');
        $mgRows = $this->modificationGroupTable->selectWith($select);

        $select = new Sql\Select($this->modificationTable->getTable());
        $select->columns(['id', 'name'])
            ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
            ->where(['item_parent_cache.item_id' => $car['id']])
            ->order(['modification.group_id', 'modification.name']);
        $names = [];
        foreach ($this->modificationTable->selectWith($select) as $row) {
            $names[$row['id']] = $row['name'];
        }

        $map = [];

        foreach ($mgRows as $mgRow) {
            $select = new Sql\Select($this->modificationTable->getTable());
            $select->columns(['id'])
                ->join('item_parent_cache', 'modification.item_id = item_parent_cache.parent_id', [])
                ->where([
                    'item_parent_cache.item_id' => $car['id'],
                    'modification.group_id'     => $mgRow['id']
                ])
                ->order(['modification.group_id', 'modification.name']);

            $ids = [];
            foreach ($this->modificationTable->selectWith($select) as $row) {
                $ids[] = (int)$row['id'];
            }

            $map[] = $ids;
        }

        $combinations = $this->groupCombinations($map);

        // get selected combinations
        $select = new Sql\Select($this->combinationModificationTable->getTable());
        $select->columns(['combination_id', 'modification_id'])
            ->join('combination', 'combination_modification.combination_id = combination.id', [])
            ->where(['combination.item_id' => $car['id']]);
        $combModRows = $this->combinationModificationTable->selectWith($select);
        $values = [];
        foreach ($combModRows as $combModRow) {
            $values[$combModRow['combination_id']][] = $combModRow['modification_id'];
        }

        // check combinations active
        /*foreach ($combinations as &$combination) {
            $combination['active'] = false;
            foreach ($values as $value) {
                if ($combination == $value) {
                    $combination['active'] = true;
                    break;
                }
            }
        }*/

        $this->view->assign([
            'combinations' => $combinations,
            'values'       => $values,
            'names'        => $names
        ]);
    }

    private function groupCombinations($groups)
    {
        $firstGroup = $groups[0];
        $otherGroups = array_slice($groups, 1);
        $combinations = [];

        if (count($otherGroups)) {
            $ogCombinations = $this->groupCombinations($otherGroups);
            foreach ($firstGroup as $id) {
                foreach ($ogCombinations as $combination) {
                    $combinations[] = array_merge([$id], $combination);
                }
            }
        } else {
            foreach ($firstGroup as $id) {
                $combinations[] = [$id];
            }
        }

        return $combinations;
    }

    public function deleteAction()
    {
        if (! $this->user()->inheritsRole('moder')) {
            return $this->forbiddenAction();
        }

        $id = (int)$this->getParam('id');

        if (! $this->modification->canDelete($id)) {
            return $this->forbiddenAction();
        }

        $this->modification->delete($id);

        return $this->redirectToCar($this->getParam('item_id'), 'modifications');
    }
}
