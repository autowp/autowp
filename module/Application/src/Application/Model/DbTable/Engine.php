<?php

namespace Application\Model\DbTable;

use Application\Db\Table;

class Engine extends Table
{
    protected $_name = 'engines';
    protected $_primary = 'id';
    protected $_rowClass = \Application\Model\DbTable\EngineRow::class;
    protected $_referenceMap = [
        'Last_Editor' => [
            'columns'       => ['last_editor_id'],
            'refTableClass' => \Application\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ]
    ];

    const MAX_NAME = 80;
}
