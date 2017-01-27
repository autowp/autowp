<?php

namespace Autowp\Votings;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\Commons\Db\Table;
use Autowp\User\Model\DbTable\User;

use DateTime;

class Votings
{
    /**
     * @var TableGateway
     */
    private $votingTable;
    
    /**
     * @var TableGateway
     */
    private $variantTable;
    
    /**
     * @var TableGateway
     */
    private $voteTable;
    
    public function __construct(Adapter $adapter)
    {
        $this->votingTable = new TableGateway('voting', $adapter);
        $this->variantTable = new TableGateway('voting_variant', $adapter);
        $this->voteTable = new TableGateway('voting_variant_vote', $adapter);
    }

    private function canVote($voting, $userId)
    {
        if (! $userId) {
            return false;
        }

        $now = new DateTime();

        $beginDate = Table\Row::getDateTimeByColumnType('date', $voting['begin_date']);
        if ($beginDate >= $now) {
            return false;
        }
        $endDate   = Table\Row::getDateTimeByColumnType('date', $voting['end_date']);
        if ($endDate <= $now) {
            return false;
        }

        $voted = $this->voteTable->select(function(Sql\Select $select) use ($voting, $userId) {
            $select
                ->join(
                    'voting_variant',
                    'voting_variant_vote.voting_variant_id = voting_variant.id',
                    []
                )
                ->where([
                    'voting_variant.voting_id'    => $voting['id'],
                    'voting_variant_vote.user_id' => $userId
                ])
                ->limit(1);
        })->current();

        if ($voted) {
            return false;
        }

        return true;
    }

    public function getVoting($id, $filter, $userId)
    {
        $voting = $this->votingTable->select([
            'id' => (int)$id
        ])->current();

        if (! $voting) {
            return null;
        }

        $variants = [];
        $vvRows = $this->variantTable->select(function(Sql\Select $select) use ($voting) {
            $select
                ->where(['voting_id = ?' => $voting['id']])
                ->order('position');
        });

        $maxVotes = $minVotes = null;
        foreach ($vvRows as $vvRow) {
            switch ($filter) {
                case 1:
                    $row = $this->voteTable->select(function(Sql\Select $select) use ($vvRow) {
                        $select
                            ->columns(['count' => new Sql\Expression('count(1)')])
                            ->join('users', 'voting_variant_vote.user_id = users.id', [])
                            ->where([
                                'voting_variant_vote.voting_variant_id' => $vvRow['id'],
                                'users.pictures_added > 100'
                            ]);
                    })->current();
                    $votes = $row['count'];
                    break;

                default:
                    $votes = $vvRow['votes'];
                    break;
            }

            $variants[] = [
                'id'      => $vvRow['id'],
                'name'    => $vvRow['name'],
                'text'    => $vvRow['text'],
                'votes'   => $votes,
                'percent' => 0,
                'isMax'   => false,
                'isMin'   => false
            ];

            if (is_null($maxVotes) || $votes > $maxVotes) {
                $maxVotes = $votes;
            }
            if (is_null($minVotes) || $votes < $minVotes) {
                $minVotes = $votes;
            }
        }

        $minVotesPercent = 0;
        if ($maxVotes > 0) {
            $minVotesPercent = ceil(100 * $minVotes / $maxVotes);
        }

        foreach ($variants as &$variant) {
            if ($maxVotes > 0) {
                $variant['percent'] = round(100 * $variant['votes'] / $maxVotes, 2);
                $variant['isMax'] = $variant['percent'] >= 99;
                $variant['isMin'] = $variant['percent'] <= $minVotesPercent;
            }
        }

        $beginDate = Table\Row::getDateTimeByColumnType('date', $voting['begin_date']);
        $endDate   = Table\Row::getDateTimeByColumnType('date', $voting['end_date']);

        return [
            'canVote'  => $this->canVote($voting, $userId),
            'voting'   => [
                'id'           => $voting['id'],
                'name'         => $voting['name'],
                'text'         => $voting['text'],
                'multivariant' => $voting['multivariant'],
                'beginDate'    => $beginDate,
                'endDate'      => $endDate
            ],
            'variants' => $variants,
            'maxVotes' => $maxVotes,
            'filter'   => $filter
        ];
    }

    public function getVotes($id)
    {
        $variant = $this->variantTable->select([
            'id' => (int)$id
        ])->current();

        if (! $variant) {
            return null;
        }

        $uTable = new User();
        $users = $uTable->fetchAll(
            $uTable->select(true)
                ->join('voting_variant_vote', 'users.id = voting_variant_vote.user_id', null)
                ->where('voting_variant_vote.voting_variant_id = ?', $variant->id)
        );

        return [
            'users' => $users
        ];
    }

    public function vote($id, $variantId, $userId)
    {
        $voting = $this->votingTable->select([
            'id' => (int)$id
        ])->current();

        if (! $voting) {
            return false;
        }

        if (! $this->canVote($voting, $userId)) {
            return false;
        }

        $variantId = (array)$variantId;

        if (count($variantId) <= 0) {
            return false;
        }

        $vvRows = $this->variantTable->select([
            'voting_id' => $voting['id'],
            new Sql\Predicate\In('id', $variantId)
        ]);

        if (! $voting['multivariant']) {
            if (count($vvRows) > 1) {
                return false;
            }
        }

        foreach ($vvRows as $vvRow) {
            $vvvRow = $this->voteTable->select([
                'voting_variant_id' => $vvRow['id'],
                'user_id'           => $userId
            ])->current();
            if (! $vvvRow) {
                $this->voteTable->insert([
                    'voting_variant_id' => $vvRow['id'],
                    'user_id'           => $userId,
                    'timestamp'         => new Sql\Expression('now()')
                ]);
            }

            $this->updateVariantVotesCount($vvRow['id']);
        }

        $this->updateVotingVotesCount($voting['id']);

        return true;
    }

    private function updateVariantVotesCount($variantId)
    {
        $count = $this->voteTable->select(function(Sql\Select $select) use ($variantId) {
            $select
                ->columns(['count' => new Sql\Expression('count(1)')])
                ->where(['voting_variant_id' => $variantId]);
        })->current();

        $this->variantTable->update([
            'votes' => $count['count']
        ], [
            'id' => $variantId
        ]);
    }

    private function updateVotingVotesCount($votingId)
    {
        $count = $this->voteTable->select(function(Sql\Select $select) use ($votingId) {
            $select
                ->columns(['count' => new Sql\Expression('count(distinct voting_variant_vote.user_id)')])
                ->join('voting_variant', 'voting_variant_vote.voting_variant_id = voting_variant.id', [])
                ->where(['voting_variant.voting_id' => $votingId]);
        })->current();
        $this->votingTable->update([
            'votes' => $count['count']
        ], [
            'id' => $votingId
        ]);
    }
}
