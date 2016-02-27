<?php

class Moder_DesignProjectsController extends Zend_Controller_Action
{

    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }
    
    public function designProjectAction()
    {
        $dpTable = new Design_Projects();
        
        $dpRow = $dpTable->find($this->getParam('design_project_id'))->current();
        if (!$dpRow) {
            return $this->_forward('notfound', 'error', 'default');
        }
        
        $carTable = new Cars();
        
        $carRows = $carTable->fetchAll(array(
            'design_project_id = ?' => $dpRow->id
        ));
        
        $cars = [];
        
        foreach ($carRows as $carRow) {
            $cars[] = [
                'url' => $this->_helper->url->url([
                    'module'     => 'moder',
                    'controller' => 'cars',
                    'action'     => 'car',
                    'car_id'     => $carRow->id,
                    'tab'        => 'catalogue'
                ], 'default', null),
                'name' => $carRow->getFullName()
            ];
        }
        
        $this->view->assign([
            'project' => $dpRow,
            'cars' => $cars
        ]);
    }
}