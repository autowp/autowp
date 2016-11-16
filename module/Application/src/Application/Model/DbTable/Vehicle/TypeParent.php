<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Db\Table;
use Application\Model\DbTable\Vehicle\Type as VehicleType;

class TypeParent extends Table
{
    protected $_name = 'car_types_parents';

    public function rebuild()
    {
        $this->delete([]);

        $table = new VehicleType();

        $this->rebuildStep($table, [0], 0);
    }

    private function rebuildStep(VehicleType $table, $id, $level)
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
                'id'        => intval($cat_id),
                'parent_id' => intval($cat_id),
                'level'     => $level
            ]);

            $this->rebuildStep($table, array_merge([$cat_id], $id), $level + 1);
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
