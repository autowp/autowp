<?php

namespace Application\Model;

use Exception;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Application\Model\DbTable\Modification as ModificationTable;

class Modification
{
    /**
     * @var TableGateway
     */
    private $modTable;

    public function __construct(TableGateway $modificationTable)
    {
        $this->modTable = $modificationTable;
    }

    public function canDelete(int $id)
    {
        $select = new Sql\Select($this->modTable->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where(['modification_picture.modification_id' => $id]);

        $row = $this->modTable->selectWith($select)->current();

        return $row ? $row['count'] <= 0 : true;
    }

    public function delete(int $id)
    {
        if (! $this->canDelete($id)) {
            throw new Exception("Modification can not be deleted");
        }

        $this->modTable->delete([
            'id = ?' => $id
        ]);
    }
}
