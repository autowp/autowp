<?php

namespace Application\Model\DbTable\Item;

use Autowp\Commons\Db\Table;

class ParentLanguage extends Table
{
    protected $_name = 'item_parent_language';
    protected $_primary = ['item_id', 'parent_id', 'language'];

    const MAX_NAME = 255;
}
