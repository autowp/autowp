<?php

class Moder_ModificationController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    /**
     * @param Cars_Row $car
     * @return string
     */
    private function carModerUrl(Cars_Row $car, $full = false, $tab = null)
    {
        return $this->view->serverUrl(
            $this->_helper->url->url(array(
                'module'     => 'moder',
                'controller' => 'cars',
                'action'     => 'car',
                'car_id'     => $car->id,
                'tab'        => $tab
            ), 'default', true)
        );
    }

    /**
     * @param Cars_Row $car
     * @return void
     */
    private function redirectToCar(Cars_Row $car, $tab = null)
    {
        return $this->redirect($this->carModerUrl($car, true, $tab));
    }

    public function addAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mTable = new Modification();
        $mgTable = new Modification_Group();

        $groupOptions = array(
            '' => 'без группы'
        );

        foreach ($mgTable->fetchAll() as $mgRow) {
            $groupOptions[$mgRow->id] = $mgRow->name;
        }

        $form = new Application_Form_Modification(array(
            'groupOptions' => $groupOptions,
            'action'       => $this->_helper->url->url()
        ));

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {

            $values = $form->getValues();

            $mRow = $mTable->createRow(array(
                'car_id'   => $car->id,
                'name'     => $values['name'],
                'group_id' => $values['group_id'] ? $values['group_id'] : null
            ));
            $mRow->save();

            return $this->redirectToCar($car, 'modifications');
        }

        $this->view->assign(array(
            'form'   => $form,
            'carUrl' => $this->carModerUrl($car, false, 'modifications')
        ));
    }

    public function editAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mTable = new Modification();

        $mRow = $mTable->find($this->getParam('modification_id'))->current();
        if (!$mRow) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mgTable = new Modification_Group();
        $mvTable = new Modification_Value();

        $groupOptions = array(
            '' => 'без группы'
        );

        foreach ($mgTable->fetchAll() as $mgRow) {
            $groupOptions[$mgRow->id] = $mgRow->name;
        }

        $form = new Application_Form_Modification(array(
            'groupOptions' => $groupOptions,
            'action'       => $this->_helper->url->url()
        ));

        $form->populate(array(
            'name'     => $mRow->name,
            'group_id' => $mRow->group_id
        ));

        $request = $this->getRequest();
        if ($request->isPost() && $form->isValid($request->getPost())) {

            $values = $form->getValues();

            $mRow->setFromArray(array(
                'name'     => $values['name'],
                'group_id' => $values['group_id'] ? $values['group_id'] : null
            ));
            $mRow->save();

            return $this->redirectToCar($car, 'modifications');
        }

        $modValues = [];
        $mvRows = $mvTable->fetchAll(array(
            'modification_id' => $mRow->id
        ), 'position');

        foreach ($mvRows as $mvRow) {
            $modValues[] = array(
                'name' => $mvRow>value,
                'id'   => $mvRow->id
            );
        }

        $this->view->assign(array(
            'form'   => $form,
            'carUrl' => $this->carModerUrl($car, false, 'modifications'),
            'values' => $modValues
        ));
    }

    public function mapAction()
    {
        $cars = $this->_helper->catalogue()->getCarTable();

        $car = $cars->find($this->_getParam('car_id'))->current();
        if (!$car) {
            return $this->_forward('notfound', 'error', 'default');
        }

        $mTable = new Modification();

        $mRows = $mTable->fetchAll(
            $mTable->select(true)
                ->join('car_parent_cache', 'modification.car_id = car_parent_cache.car_id', null)
                ->where('car_parent_cache.parent_id = ?', $car->id)
                ->order(['modification.group_id', 'modification.name'])
        );

        /*$map = [];
        foreach ($mRows as $mRow1) {
            foreach ($mRows as $mRow2) {
                $map[] = array(
                    'first'  => $mRow1->toArray(),
                    'second' => $mRow2->toArray()
                );
            }
        }*/

        $this->view->assign(array(
            'rows' => $mRows->toArray()
        ));
    }
}