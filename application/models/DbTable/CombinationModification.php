<?php

namespace Application\Model\DbTable;

use Project_Db_Table;

class CombinationModification extends Project_Db_Table
{
    protected $_name = 'combination_modification';
    protected $_primary = ['combination_id', 'modification_id'];
}