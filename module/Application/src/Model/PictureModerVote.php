<?php

namespace Application\Model;

use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function count;

class PictureModerVote
{
    public const MAX_LENGTH = 80;

    private TableGateway $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function getVotes(int $pictureId): array
    {
        $rows = $this->table->select([
            'picture_id' => $pictureId,
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'user_id' => (int) $row['user_id'],
                'reason'  => $row['reason'],
                'vote'    => $row['vote'],
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanPluginMixedKeyNoKey
     */
    public function getNegativeVotes(int $pictureId): array
    {
        $rows = $this->table->select([
            'picture_id' => $pictureId,
            'vote = 0',
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'user_id' => (int) $row['user_id'],
                'reason'  => $row['reason'],
            ];
        }

        return $result;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function add(int $pictureId, int $userId, int $vote, string $reason): void
    {
        $this->table->insert([
            'user_id'    => $userId,
            'picture_id' => $pictureId,
            'day_date'   => new Sql\Expression('NOW()'),
            'reason'     => $reason,
            'vote'       => $vote ? 1 : 0,
        ]);
    }

    public function delete(int $pictureId, int $userId): void
    {
        $this->table->delete([
            'user_id = ?'    => $userId,
            'picture_id = ?' => $pictureId,
        ]);
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod
     */
    public function getVoteCount(int $pictureId): array
    {
        $select = new Sql\Select($this->table->getTable());
        $select
            ->columns([
                'vote'  => new Sql\Expression('sum(if(vote, 1, -1))'),
                'count' => new Sql\Expression('count(1)'),
            ])
            ->where(['picture_id' => $pictureId]);

        $row = $this->table->selectWith($select)->current();
        return [
            'vote'  => (int) $row['vote'],
            'count' => (int) $row['count'],
        ];
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanPluginMixedKeyNoKey
     */
    public function getVoteCountArray(array $ids): array
    {
        if (! count($ids)) {
            return [];
        }

        $select = new Sql\Select($this->table->getTable());
        $select
            ->columns([
                'picture_id',
                'vote'  => new Sql\Expression('sum(if(vote, 1, -1))'),
                'count' => new Sql\Expression('count(1)'),
            ])
            ->where([new Sql\Predicate\In('picture_id', $ids)])
            ->group('picture_id');

        $rows = $this->table->selectWith($select);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['picture_id']] = [
                'moder_votes'       => (int) $row['vote'],
                'moder_votes_count' => (int) $row['count'],
            ];
        }

        return $result;
    }

    public function hasVote(int $pictureId, int $userId): bool
    {
        return (bool) $this->table->select([
            'picture_id' => $pictureId,
            'user_id'    => $userId,
        ])->current();
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod, PhanPluginMixedKeyNoKey
     */
    public function getPositiveVotesCount(int $pictureId): int
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'picture_id' => $pictureId,
                'vote > 0',
            ]);

        $row = $this->table->selectWith($select)->current();
        return $row ? (int) $row['count'] : 0;
    }

    /**
     * @suppress PhanDeprecatedFunction, PhanUndeclaredMethod, PhanPluginMixedKeyNoKey
     */
    public function getNegativeVotesCount(int $pictureId): int
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'picture_id' => $pictureId,
                'vote = 0',
            ]);

        $row = $this->table->selectWith($select)->current();
        return $row ? (int) $row['count'] : 0;
    }
}
