<?php

namespace Application\Model\DbTable\Vehicle;

use Autowp\Commons\Db\Table;

class TypeParent extends Table
{
    protected $_name = 'car_types_parents';

    public function rebuild()
    {
        $this->delete([]);

        $table = new Type();

        $this->rebuildStep($table, [0], 0);
    }

    private function rebuildStep(Type $table, $id, $level)
    {
        $select = $table->select()
            ->from($table, 'id');
        if ($id[0] == 0) {
            $select->where('parent_id is null');
        } else {
            $select->where('parent_id = ?', $id[0]);
        }

        foreach ($table->getAdapter()->fetchCol($select) as $catId) {
            $this->insert([
                'id'        => intval($catId),
                'parent_id' => intval($catId),
                'level'     => $level
            ]);

            $this->rebuildStep($table, array_merge([$catId], $id), $level + 1);
        }

        --$level;
        foreach ($id as $tid) {
            if ($tid && ( $id[0] != $tid )) {
                $this->insert([
                    'id'        => $id[0],
                    'parent_id' => $tid,
                    'level'     => --$level
                ]);
            }
        }
    }
}
