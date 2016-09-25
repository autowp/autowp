<?php

namespace Application\Model;

use Application\Model\DbTable\Modification as ModificationTable;

use Exception;

class Modification
{
    private $modTable;

    public function __construct()
    {
        $this->modTable = new ModificationTable();
    }

    public function canDelete($id)
    {
        $db = $this->modTable->getAdapter();

        $picturesCount = $db->fetchOne(
            $db->select()
                ->from('modification_picture', 'count(1)')
                ->where('modification_picture.modification_id = ?', (int)$id)
        );

        return !$picturesCount;
    }

    public function delete($id)
    {
        if (!$this->canDelete($id)) {
            throw new Exception("Modification can not be deleted");
        }

        $row = $this->modTable->find($id)->current();
        if ($row) {
            $row->delete();
        }
    }
}