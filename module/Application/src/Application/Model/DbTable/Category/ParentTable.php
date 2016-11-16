<?php

namespace Application\Model\DbTable\Category;

use Application\Db\Table;
use Application\Model\DbTable\Category;

class ParentTable extends Table
{
    protected $_name = 'category_parent';
    protected $_primary = ['category_id', 'parent_id'];

    public function rebuild()
    {
        $this->delete([]);

        $table = new Category();

        $this->_rebuild($table, [0], 0);
    }

    private function _rebuild(Category $table, $id, $level)
    {
        $select = $table->select()
            ->from($table, 'id');
        if ($id[0] == 0) {
            $select->where('parent_id is null');
        } else {
            $select->where('parent_id = ?', $id[0]);
        }

        foreach ($table->getAdapter()->fetchCol($select) as $cat_id) {
            $this->insert([
                'category_id' => intval($cat_id),
                'parent_id'   => intval($cat_id),
                'level'       => $level
            ]);

            $this->_rebuild($table, array_merge([$cat_id], $id), $level + 1);
        }

        --$level;
        foreach ($id as $tid) {
            if ($tid && ( $id[0] != $tid )) {
                $this->insert([
                    'category_id' => $id[0],
                    'parent_id'   => $tid,
                    'level'        => --$level
                ]);
            }
        }
    }
}
