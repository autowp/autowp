<?php

namespace Application\Model\DbTable\Modification;

use Application\Db\Table;

class Value extends Table
{
    protected $_name = 'modification_value';
    protected $_primary = ['id'];
}