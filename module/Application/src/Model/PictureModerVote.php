<?php

namespace Application\Model;

use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;

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
     * @throws Exception
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

        $row = currentFromResultSetInterface($this->table->selectWith($select));
        return [
            'vote'  => (int) $row['vote'],
            'count' => (int) $row['count'],
        ];
    }

    /**
     * @throws Exception
     */
    public function hasVote(int $pictureId, int $userId): bool
    {
        return (bool) currentFromResultSetInterface($this->table->select([
            'picture_id' => $pictureId,
            'user_id'    => $userId,
        ]));
    }

    /**
     * @throws Exception
     */
    public function getPositiveVotesCount(int $pictureId): int
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'picture_id' => $pictureId,
                'vote > 0',
            ]);

        $row = currentFromResultSetInterface($this->table->selectWith($select));
        return $row ? (int) $row['count'] : 0;
    }

    /**
     * @throws Exception
     */
    public function getNegativeVotesCount(int $pictureId): int
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['count' => new Sql\Expression('count(1)')])
            ->where([
                'picture_id' => $pictureId,
                'vote = 0',
            ]);

        $row = currentFromResultSetInterface($this->table->selectWith($select));
        return $row ? (int) $row['count'] : 0;
    }
}
