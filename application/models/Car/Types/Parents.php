<?php

class Car_Types_Parents extends Project_Db_Table
{
    protected $_name = 'car_types_parents';

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

        $table = new Car_Types();

        $this->rebuildStep($table, array(0), 0);
    }

    private function rebuildStep(Car_Types $table, $id, $level)
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
                'id'        =>  intval($cat_id),
                'parent_id' =>  intval($cat_id),
                'level'     =>  $level
            ));

            $this->rebuildStep($table, array_merge(array($cat_id), $id), $level + 1);
        }

        --$level;
        foreach ($id as $tid) {
            if ( $tid && ( $id[0] != $tid ) ) {

                $this->insert(array(
                    'id'        =>  $id[0],
                    'parent_id' =>  $tid,
                    'level'     =>  --$level
                ));
            }
        }
    }
}