<?php

namespace Application\Model\DbTable;

use Zend_Db_Table;

class CombinationExcludeModification extends Zend_Db_Table
{
    protected $_name = 'combination_exclude_modification';
    protected $_primary = ['combination_id', 'modification_id'];
}
