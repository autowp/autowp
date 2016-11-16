<?php

namespace Application\Model\DbTable\Attr;

use Application\Db\Table;

class Type extends Table
{
    protected $_name = 'attrs_types';
    protected $_rowClass = \Application\Model\DbTable\Attr\TypeRow::class;
}
