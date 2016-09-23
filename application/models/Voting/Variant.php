<?php

use Application\Db\Table;

class Voting_Variant extends Table
{
    protected $_name = 'voting_variant';
    protected $_primary = 'id';
    protected $_referenceMap = [
        'Voting' => [
            'columns'       => ['voting_id'],
            'refTableClass' => 'Voting',
            'refColumns'    => ['id']
        ]
    ];
}