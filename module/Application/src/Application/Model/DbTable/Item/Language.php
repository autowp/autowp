<?php

namespace Application\Model\DbTable\Item;

use Autowp\Commons\Db\Table;

class Language extends Table
{
    protected $_name = 'item_language';
    protected $_primary = ['item_id', 'language'];

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => \Application\Model\DbTable\Item::class,
            'refColumns'    => ['id']
        ],
    ];
}
