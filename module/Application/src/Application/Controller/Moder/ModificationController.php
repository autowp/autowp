<?php

use Application\Form\Modification as ModificationForm;
use Application\Model\DbTable\CombinationModification;
use Application\Model\DbTable\Modification as ModificationTable;
use Application\Model\DbTable\Modification\Group as ModificationGroup;
use Application\Model\DbTable\Vehicle\Row as VehicleRow;
use Application\Model\Modification;

class Moder_ModificationController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (! $this->_helper->user()->inheritsRole('moder')) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    /**
     * @param VehicleRow $car
     * @return string
     */
    private function carModerUrl($carId, $full = false, $tab = null)
    {
        return $this->view->serverUrl(
            $this->_helper->url->url([
                'module'     => 'moder',
                'controller' => 'cars',
                'action'     => 'car',
                'car_id'     => $carId,
                'tab'        => $tab
            ], 'default', true)
        );
    }

    /**
     * @param VehicleRow $car
     * @return void
     */
    private function redirectToCar($carId, $tab = null)
    {
        return $this->redirect($this->carModerUrl($carId, true, $tab));
    }

    public function addAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->_getParam('car_id'))->current();
        if (! $car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mTable = new ModificationTable();
        $mgTable = new ModificationGroup();

        $groupOptions = [
            '' => 'без группы'
        ];

        foreach ($mgTable->fetchAll() as $mgRow) {
            $groupOptions[$mgRow->id] = $mgRow->name;
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

            $mRow = $mTable->createRow([
                'car_id'           => $car->id,
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
            $mRow->save();

            return $this->redirectToCar($car->id, 'modifications');
        }

        $this->view->assign([
            'form'   => $form,
            'carUrl' => $this->carModerUrl($car->id, false, 'modifications')
        ]);
    }

    public function editAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->_getParam('car_id'))->current();
        if (! $car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mTable = new ModificationTable();

        $mRow = $mTable->find($this->getParam('modification_id'))->current();
        if (! $mRow) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mgTable = new ModificationGroup();

        $groupOptions = [
            '' => 'без группы'
        ];

        foreach ($mgTable->fetchAll() as $mgRow) {
            $groupOptions[$mgRow->id] = $mgRow->name;
        }

        $form = new ModificationForm([
            'groupOptions' => $groupOptions,
            'action'       => $this->_helper->url->url()
        ]);

        $form->populate([
            'name'             => $mRow->name,
            'group_id'         => $mRow->group_id,
            'begin_year'       => $mRow->begin_year,
            'end_year'         => $mRow->end_year,
            'begin_month'      => $mRow->begin_month,
            'end_month'        => $mRow->end_month,
            'begin_model_year' => $mRow->begin_model_year,
            'end_model_year'   => $mRow->end_model_year,
            'today'            => $mRow->today ? 1 : 0,
            'produced'         => $mRow->produced,
            'produced_exactly' => $mRow->produced_exactly,
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

            $mRow->setFromArray([
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
            $mRow->save();

            return $this->redirectToCar($car->id, 'modifications');
        }

        $this->view->assign([
            'form'   => $form,
            'carUrl' => $this->carModerUrl($car->id, false, 'modifications'),
        ]);
    }

    public function mapAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->_getParam('car_id'))->current();
        if (! $car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mgTable = new ModificationGroup();

        $mgRows = $mgTable->fetchAll(
            $mgTable->select(true)
                ->join('modification', 'modification_group.id = modification.group_id', null)
                ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
                ->group('modification_group.id')
        );

        $mTable = new ModificationTable();
        $db = $mTable->getAdapter();

        $names = $db->fetchPairs(
            $db->select()
                ->from($mTable->info('name'), ['id', 'name'])
                ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
                ->where('car_parent_cache.car_id = ?', $car->id)
                ->order(['modification.group_id', 'modification.name'])
        );

        $map = [];

        foreach ($mgRows as $mgRow) {
            $map[] = $db->fetchCol(
                $db->select()
                    ->from($mTable->info('name'), 'id')
                    ->join('car_parent_cache', 'modification.car_id = car_parent_cache.parent_id', null)
                    ->where('car_parent_cache.car_id = ?', $car->id)
                    ->where('modification.group_id = ?', $mgRow->id)
                    ->order(['modification.group_id', 'modification.name'])
            );
        }

        $combinations = $this->groupCombinations($map);

        // get selected combinations
        $combModTable = new CombinationModification();
        $db = $combModTable->getAdapter();
        $combModRows = $db->fetchAll(
            $db->select(true)
                ->from($combModTable->info('name'), ['combination_id', 'modification_id'])
                ->join('combination', 'combination_modification.combination_id = combination.id', null)
                ->where('combination.car_id = ?', $car->id)
        );
        $values = [];
        foreach ($combModRows as $combModRow) {
            $values[$combModRow->combination_id][] = $combModRow['modification_id'];
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

        //print_r($combinations); exit;

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
        $id = (int)$this->getParam('id');

        $modModel = new Modification();

        if (! $modModel->canDelete($id)) {
            return $this->forward('forbidden', 'error');
        }

        $modModel->delete($id);

        return $this->redirectToCar($this->getParam('car_id'), 'modifications');
    }
}
