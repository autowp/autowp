<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class CombinationModification extends Table
{
    protected $_name = 'combination_modification';
    protected $_primary = ['combination_id', 'modification_id'];
}