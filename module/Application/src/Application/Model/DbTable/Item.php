<?php

namespace Application\Model\DbTable;

use Autowp\User\Model\DbTable\User;

use Autowp\Commons\Db\Table;

class Item extends Table
{
    const MAX_NAME = 100;

    protected $_name = 'item';
    protected $_referenceMap = [
        'Meta_Last_Editor' => [
            'columns'       => ['meta_last_editor_id'],
            'refTableClass' => User::class,
            'refColumns'    => ['id']
        ],
        'Tech_Last_Editor' => [
            'columns'       => ['tech_last_editor_id'],
            'refTableClass' => User::class,
            'refColumns'    => ['id']
        ],
        'Engine' => [
            'columns'       => ['engine_item_id'],
            'refTableClass' => self::class,
            'refColumns'    => ['id']
        ]
    ];

    public function insert(array $data)
    {
        if (isset($data['body'])) {
            $data['body'] = trim($data['body']);
        }
        $data['add_datetime'] = date('Y-m-d H:i:s');

        return parent::insert($data);
    }
}
