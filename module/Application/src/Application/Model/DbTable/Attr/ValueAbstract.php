<?php

namespace Application\Model\DbTable\Attr;

use Autowp\Commons\Db\Table;

class ValueAbstract extends Table
{
    protected $_primary = ['attribute_id', 'item_id'];
}
