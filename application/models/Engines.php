<?php

use Application\Db\Table;

class Engines extends Table
{
    protected $_name = 'engines';
    protected $_primary = 'id';
    protected $_rowClass = 'Engine_Row';
    protected $_referenceMap = [
        'Last_Editor' => [
            'columns'       => ['last_editor_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ]
    ];

    const MAX_NAME = 80;
}