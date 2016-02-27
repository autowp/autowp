<?php
class Moder_PerspectivesController extends Zend_Controller_Action
{
    public function preDispatch()
    {
        parent::preDispatch();

        if (!$this->_helper->user()->inheritsRole('moder') ) {
            return $this->_forward('forbidden', 'error', 'default');
        }
    }

    public function indexAction()
    {
        $prspModel = new Perspectives();
        $prspGroupsModel = new Perspectives_Groups();
        $prspGroupsPrspModel = new Perspectives_Groups_Perspectives();
        $prspPagesModel = new Perspectives_Pages();

        $data = array();
        foreach ($prspPagesModel->fetchAll(null, 'id') as $page) {
            $groups = array();

            $groupRows = $prspGroupsModel->fetchAll(array(
                'page_id = ?' => $page->id
            ), 'position');
            foreach ($groupRows as $groupRow) {
                $perspectives = array();

                $perspectiveRows = $prspModel->fetchAll(
                    $prspModel->select(true)
                        ->join('perspectives_groups_perspectives', 'perspectives.id=perspectives_groups_perspectives.perspective_id', null)
                        ->where('perspectives_groups_perspectives.group_id = ?', $groupRow->id)
                        ->order('perspectives_groups_perspectives.position')
                );
                foreach ($perspectiveRows as $perspectiveRow) {
                    $perspectives[] = array(
                        'id'   => $perspectiveRow->id,
                        'name' => $perspectiveRow->name
                    );
                }

                $groups[] = array(
                    'id'           => $groupRow->id,
                    'name'         => $groupRow->name,
                    'perspectives' => $perspectives
                );
            }

            $data[] = array(
                'id'    => $page->id,
                'name'  => $page->name,
                'groups'=> $groups
            );
        }

        $this->view->pages = $data;
    }

}