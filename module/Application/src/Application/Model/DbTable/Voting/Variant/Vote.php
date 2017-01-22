<?php

namespace Application\Model\DbTable\Voting\Variant;

use Autowp\Commons\Db\Table;

class Vote extends Table
{
    protected $_name = 'voting_variant_vote';
    protected $_primary = ['voting_variant_id', 'user_id'];
    protected $_referenceMap = [
        'VotingVariant' => [
            'columns'       => ['voting_variant_id'],
            'refTableClass' => 'Application\\Model\\DbTable\\Voting\\Variant',
            'refColumns'    => ['id']
        ],
        'User' => [
            'columns'       => ['user_id'],
            'refTableClass' => \Autowp\User\Model\DbTable\User::class,
            'refColumns'    => ['id']
        ]
    ];
}
