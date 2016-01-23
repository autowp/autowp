<?php
class InfoController extends Zend_Controller_Action
{
    private function _loadSpecs($table, $parentId)
    {
        if ($parentId) {
            $filter = array('parent_id = ?' => $parentId);
        } else {
            $filter = array('parent_id is null');
        }

        $result = [];
        foreach ($table->fetchAll($filter, 'short_name') as $row) {
            $result[] = array(
                'id'         => $row->id,
                'short_name' => $row->short_name,
                'name'       => $row->name,
                'childs'     => $this->_loadSpecs($table, $row->id)
            );
        }

        return $result;
    }

    public function specAction()
    {
        $table = new Spec();

        $this->view->assign(array(
            'items' => $this->_loadSpecs($table, null)
        ));
    }
}