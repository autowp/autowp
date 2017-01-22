<?php

namespace Application\Model\DbTable\Attr;

use Autowp\Commons\Db\Table;

class Type extends Table
{
    protected $_name = 'attrs_types';
    protected $_rowClass = TypeRow::class;
}
