<?php

namespace Application\Model\DbTable\Attr;

use Autowp\Commons\Db\Table;

class Value extends Table
{
    protected $_name = 'attrs_values';
    protected $_primary = ['attribute_id', 'item_id'];
}
