<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class Factory extends Table
{
    protected $_name = 'factory';
    protected $_primary = 'id';
    protected $_rowClass = \Application\Model\DbTable\FactoryRow::class;
}
