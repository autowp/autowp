<?php

use Application\Db\Table;

class Voting_Variant_Vote extends Table
{
    protected $_name = 'voting_variant_vote';
    protected $_primary = ['voting_variant_id', 'user_id'];
    protected $_referenceMap = [
        'Voting_Variant' => [
            'columns'       => ['voting_variant_id'],
            'refTableClass' => 'Voting_Variant',
            'refColumns'    => ['id']
        ],
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => 'Users',
            'refColumns'    => ['id']
        ]
    ];
}