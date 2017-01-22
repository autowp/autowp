<?php

namespace Application\Model\DbTable\Voting;

use Autowp\Commons\Db\Table;

class Variant extends Table
{
    protected $_name = 'voting_variant';
    protected $_primary = 'id';
    protected $_referenceMap = [
        'Voting' => [
            'columns'       => ['voting_id'],
            'refTableClass' => 'Application\\Model\\DbTable\\Voting',
            'refColumns'    => ['id']
        ]
    ];
}
