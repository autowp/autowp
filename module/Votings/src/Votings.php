<?php

namespace Autowp\Votings;

use DateTime;
use Exception;
use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Autowp\Commons\Db\Table\Row;

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

    public function __construct(
        TableGateway $votingTable,
        TableGateway $variantTable,
        TableGateway $voteTable
    ) {
        $this->votingTable = $votingTable;
        $this->variantTable = $variantTable;
        $this->voteTable = $voteTable;
    }

    /**
     * @param $voting
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    private function canVote($voting, int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $now = new DateTime();

        $beginDate = Row::getDateTimeByColumnType('date', $voting['begin_date']);
        if ($beginDate >= $now) {
            return false;
        }
        $endDate   = Row::getDateTimeByColumnType('date', $voting['end_date']);
        if ($endDate <= $now) {
            return false;
        }

        $voted = $this->voteTable->select(function (Sql\Select $select) use ($voting, $userId) {
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

    /**
     * @param int $id
     * @param int $filter
     * @param int $userId
     * @return array|null
     * @throws Exception
     */
    public function getVoting(int $id, int $filter, int $userId): ?array
    {
        $voting = $this->votingTable->select([
            'id' => $id
        ])->current();

        if (! $voting) {
            return null;
        }

        $variants = [];
        $vvRows = $this->variantTable->select(function (Sql\Select $select) use ($voting) {
            $select
                ->where(['voting_id' => $voting['id']])
                ->order('position');
        });

        $maxVotes = $minVotes = null;
        foreach ($vvRows as $vvRow) {
            switch ($filter) {
                case 1:
                    $row = $this->voteTable->select(
                        /**
                         * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
                         */
                        function (Sql\Select $select) use ($vvRow) {
                            $select
                                ->columns(['count' => new Sql\Expression('count(1)')])
                                ->join('users', 'voting_variant_vote.user_id = users.id', [])
                                ->where([
                                    'voting_variant_vote.voting_variant_id' => $vvRow['id'],
                                    'users.pictures_added > 100'
                                ]);
                        }
                    )->current();
                    $votes = (int)$row['count'];
                    break;

                default:
                    $votes = (int)$vvRow['votes'];
                    break;
            }

            $variants[] = [
                'id'      => (int)$vvRow['id'],
                'name'    => $vvRow['name'],
                'text'    => $vvRow['text'],
                'votes'   => $votes,
                'percent' => 0,
                'is_max'  => false,
                'is_min'  => false
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
                $variant['is_max'] = $variant['percent'] >= 99;
                $variant['is_min'] = $variant['percent'] <= $minVotesPercent;
            }
        }

        $beginDate = Row::getDateTimeByColumnType('date', $voting['begin_date']);
        $endDate   = Row::getDateTimeByColumnType('date', $voting['end_date']);

        return [
            'id'           => (int)$voting['id'],
            'name'         => $voting['name'],
            'text'         => $voting['text'],
            'multivariant' => (bool)$voting['multivariant'],
            'begin_date'   => $beginDate,
            'end_date'     => $endDate,
            'can_vote'     => $this->canVote($voting, $userId),
            'variants'     => $variants,
            'max_votes'    => $maxVotes
        ];
    }

    public function getVotes(int $id): array
    {
        $variant = $this->variantTable->select([
            'id' => $id
        ])->current();

        if (! $variant) {
            return [];
        }

        $select = $this->voteTable->getSql()->select()
            ->where(['voting_variant_id' => $variant['id']]);

        $rows = [];
        foreach ($this->voteTable->selectWith($select) as $row) {
            $rows[] = [
                'user_id' => (int)$row['user_id']
            ];
        }

        return $rows;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     * @param int $id
     * @param int|array $variantId
     * @param int $userId
     * @return bool
     * @throws Exception
     */
    public function vote(int $id, $variantId, int $userId): bool
    {
        $voting = $this->votingTable->select([
            'id' => $id
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

    private function updateVariantVotesCount(int $variantId)
    {
        $count = $this->voteTable->select(
            /**
             * @suppress PhanDeprecatedFunction
             */
            function (Sql\Select $select) use ($variantId) {
                $select
                    ->columns(['count' => new Sql\Expression('count(1)')])
                    ->where(['voting_variant_id' => $variantId]);
            }
        )->current();

        $this->variantTable->update([
            'votes' => $count['count']
        ], [
            'id' => $variantId
        ]);
    }

    private function updateVotingVotesCount(int $votingId)
    {
        $count = $this->voteTable->select(
            /**
             * @suppress PhanDeprecatedFunction
             */
            function (Sql\Select $select) use ($votingId) {
                $select
                    ->columns(['count' => new Sql\Expression('count(distinct voting_variant_vote.user_id)')])
                    ->join('voting_variant', 'voting_variant_vote.voting_variant_id = voting_variant.id', [])
                    ->where(['voting_variant.voting_id' => $votingId]);
            }
        )->current();
        $this->votingTable->update([
            'votes' => $count['count']
        ], [
            'id' => $votingId
        ]);
    }

    public function isVotingExists(int $votingId): bool
    {
        $voting = $this->votingTable->select([
            'id' => $votingId
        ])->current();

        return (bool)$voting;
    }
}
