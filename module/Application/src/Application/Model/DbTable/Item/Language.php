<?php

namespace Application\Model\DbTable\Vehicle;

use Application\Db\Table;

class Language extends Table
{
    protected $_name = 'item_language';
    protected $_primary = ['item_id', 'language'];

    protected $_referenceMap = [
        'Car' => [
            'columns'       => ['item_id'],
            'refTableClass' => \Application\Model\DbTable\Vehicle::class,
            'refColumns'    => ['id']
        ],
    ];
}
