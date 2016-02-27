<?php

class Category_Parent extends Project_Db_Table
{
    protected $_name = 'category_parent';
    protected $_primary = array('category_id', 'parent_id');

    /*protected $_referenceMap    = array(
        'Parent' => array(
            'columns'           => array('parent_id'),
            'refTableClass'     => 'Car_Types',
            'refColumns'        => array('id')
        ),
    );*/

    public function rebuild()
    {
        $this->delete(array());

        $table = new Category();

        $this->_rebuild($table, array(0), 0);
    }

    protected function _rebuild(Category $table, $id, $level)
    {
        $select = $table->select()
            ->from($table, 'id');
        if ($id[0] == 0) {
            $select->where('parent_id is null');
        } else {
            $select->where('parent_id = ?', $id[0]);
        }

        foreach ($table->getAdapter()->fetchCol($select) as $cat_id) {
            $this->insert(array(
                'category_id' => intval($cat_id),
                'parent_id'   => intval($cat_id),
                'level'       => $level
            ));

            $this->_rebuild($table, array_merge(array($cat_id), $id), $level + 1);
        }

        --$level;
        foreach ($id as $tid) {
            if ( $tid && ( $id[0] != $tid ) ) {

                $this->insert(array(
                    'category_id' => $id[0],
                    'parent_id'   => $tid,
                    'level'        => --$level
                ));
            }
        }
    }
}