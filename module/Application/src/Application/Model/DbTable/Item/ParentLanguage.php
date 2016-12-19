<?php

namespace Application\Model\DbTable\Item;

use Application\Db\Table;

class ParentLanguage extends Table
{
    protected $_name = 'item_parent_language';
    protected $_primary = ['item_id', 'parent_id', 'language'];
}
