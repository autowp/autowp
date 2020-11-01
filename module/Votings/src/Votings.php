<?php

namespace Autowp\Votings;

use ArrayAccess;
use Autowp\Commons\Db\Table\Row;
use DateTime;
use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;
use function ceil;
use function count;
use function round;

class Votings
{
    private TableGateway $votingTable;

    private TableGateway $variantTable;

    private TableGateway $voteTable;

    public function __construct(
        TableGateway $votingTable,
        TableGateway $variantTable,
        TableGateway $voteTable
    ) {
        $this->votingTable  = $votingTable;
        $this->variantTable = $variantTable;
        $this->voteTable    = $voteTable;
    }

    /**
     * @param array|ArrayAccess $voting
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
        $endDate = Row::getDateTimeByColumnType('date', $voting['end_date']);
        if ($endDate <= $now) {
            return false;
        }

        $voted = currentFromResultSetInterface(
            $this->voteTable->select(function (Sql\Select $select) use ($voting, $userId): void {
                $select
                    ->join(
                        'voting_variant',
                        'voting_variant_vote.voting_variant_id = voting_variant.id',
                        []
                    )
                    ->where([
                        'voting_variant.voting_id'    => $voting['id'],
                        'voting_variant_vote.user_id' => $userId,
                    ])
                    ->limit(1);
            })
        );

        if ($voted) {
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     * @return ?array<string, mixed>
     */
    public function getVoting(int $id, int $filter, int $userId): ?array
    {
        $voting = currentFromResultSetInterface($this->votingTable->select(['id' => $id]));

        if (! $voting) {
            return null;
        }

        $variants = [];
        $vvRows   = $this->variantTable->select(function (Sql\Select $select) use ($voting): void {
            $select
                ->where(['voting_id' => $voting['id']])
                ->order('position');
        });

        $maxVotes = $minVotes = null;
        foreach ($vvRows as $vvRow) {
            switch ($filter) {
                case 1:
                    $row   = currentFromResultSetInterface($this->voteTable->select(
                        function (Sql\Select $select) use ($vvRow): void {
                            $select
                                ->columns(['count' => new Sql\Expression('count(1)')])
                                ->join('users', 'voting_variant_vote.user_id = users.id', [])
                                ->where([
                                    'voting_variant_vote.voting_variant_id' => $vvRow['id'],
                                    'users.pictures_added > 100',
                                ]);
                        }
                    ));
                    $votes = $row ? (int) $row['count'] : 0;
                    break;

                default:
                    $votes = (int) $vvRow['votes'];
                    break;
            }

            $variants[] = [
                'id'      => (int) $vvRow['id'],
                'name'    => $vvRow['name'],
                'text'    => $vvRow['text'],
                'votes'   => $votes,
                'percent' => 0,
                'is_max'  => false,
                'is_min'  => false,
            ];

            if ($maxVotes === null || $votes > $maxVotes) {
                $maxVotes = $votes;
            }
            if ($minVotes === null || $votes < $minVotes) {
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
                $variant['is_max']  = $variant['percent'] >= 99;
                $variant['is_min']  = $variant['percent'] <= $minVotesPercent;
            }
        }

        $beginDate = Row::getDateTimeByColumnType('date', $voting['begin_date']);
        $endDate   = Row::getDateTimeByColumnType('date', $voting['end_date']);

        return [
            'id'           => (int) $voting['id'],
            'name'         => $voting['name'],
            'text'         => $voting['text'],
            'multivariant' => (bool) $voting['multivariant'],
            'begin_date'   => $beginDate,
            'end_date'     => $endDate,
            'can_vote'     => $this->canVote($voting, $userId),
            'variants'     => $variants,
            'max_votes'    => $maxVotes,
        ];
    }

    /**
     * @return array<int, array<string, int>>
     * @throws Exception
     */
    public function getVotes(int $id): array
    {
        $variant = currentFromResultSetInterface($this->variantTable->select([
            'id' => $id,
        ]));

        if (! $variant) {
            return [];
        }

        $select = $this->voteTable->getSql()->select()
            ->where(['voting_variant_id' => $variant['id']]);

        $rows = [];
        foreach ($this->voteTable->selectWith($select) as $row) {
            $rows[] = [
                'user_id' => (int) $row['user_id'],
            ];
        }

        return $rows;
    }

    /**
     * @throws Exception
     */
    public function vote(int $id, array $variantId, int $userId): bool
    {
        $voting = currentFromResultSetInterface($this->votingTable->select(['id' => $id]));

        if (! $voting) {
            return false;
        }

        if (! $this->canVote($voting, $userId)) {
            return false;
        }

        if (count($variantId) <= 0) {
            return false;
        }

        $vvRows = $this->variantTable->select([
            'voting_id' => $voting['id'],
            new Sql\Predicate\In('id', $variantId),
        ]);

        if (! $voting['multivariant']) {
            if (count($vvRows) > 1) {
                return false;
            }
        }

        foreach ($vvRows as $vvRow) {
            $vvvRow = currentFromResultSetInterface($this->voteTable->select([
                'voting_variant_id' => $vvRow['id'],
                'user_id'           => $userId,
            ]));
            if (! $vvvRow) {
                $this->voteTable->insert([
                    'voting_variant_id' => $vvRow['id'],
                    'user_id'           => $userId,
                    'timestamp'         => new Sql\Expression('now()'),
                ]);
            }

            $this->updateVariantVotesCount($vvRow['id']);
        }

        $this->updateVotingVotesCount($voting['id']);

        return true;
    }

    private function updateVariantVotesCount(int $variantId): void
    {
        $count = currentFromResultSetInterface($this->voteTable->select(
            function (Sql\Select $select) use ($variantId): void {
                $select
                    ->columns(['count' => new Sql\Expression('count(1)')])
                    ->where(['voting_variant_id' => $variantId]);
            }
        ));

        $this->variantTable->update([
            'votes' => $count ? $count['count'] : 0,
        ], [
            'id' => $variantId,
        ]);
    }

    private function updateVotingVotesCount(int $votingId): void
    {
        $count = currentFromResultSetInterface($this->voteTable->select(
            function (Sql\Select $select) use ($votingId): void {
                $select
                    ->columns(['count' => new Sql\Expression('count(distinct voting_variant_vote.user_id)')])
                    ->join('voting_variant', 'voting_variant_vote.voting_variant_id = voting_variant.id', [])
                    ->where(['voting_variant.voting_id' => $votingId]);
            }
        ));
        $this->votingTable->update([
            'votes' => $count ? $count['count'] : 0,
        ], [
            'id' => $votingId,
        ]);
    }

    public function isVotingExists(int $votingId): bool
    {
        $voting = currentFromResultSetInterface($this->votingTable->select(['id' => $votingId]));

        return (bool) $voting;
    }
}
