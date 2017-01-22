<?php

namespace Application\Model\DbTable\Modification;

use Autowp\Commons\Db\Table;

class Value extends Table
{
    protected $_name = 'modification_value';
    protected $_primary = ['id'];
}
