<?php

namespace Autowp\User\Model;

use Exception;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;
use function md5;
use function uniqid;

class UserPasswordRemind
{
    private TableGateway $table;

    private string $salt;

    public function __construct(TableGateway $table, string $salt)
    {
        $this->table = $table;
        $this->salt  = $salt;
    }

    public function garbageCollect(): int
    {
        return (int) $this->table->delete([
            'created < DATE_SUB(NOW(), INTERVAL 10 DAY)',
        ]);
    }

    public function deleteToken(string $token): void
    {
        $this->table->delete([
            'hash' => $token,
        ]);
    }

    /**
     * @throws Exception
     */
    public function getUserId(string $token): int
    {
        $uprRow = currentFromResultSetInterface($this->table->select([
            'hash' => $token,
            'created > DATE_SUB(NOW(), INTERVAL 10 DAY)',
        ]));

        return $uprRow ? (int) $uprRow['user_id'] : 0;
    }

    /**
     * @throws Exception
     */
    public function createToken(int $userId): string
    {
        do {
            $token  = md5($this->salt . uniqid());
            $exists = (bool) currentFromResultSetInterface($this->table->select([
                'hash' => $token,
            ]));
        } while ($exists);

        $this->table->insert([
            'user_id' => $userId,
            'hash'    => $token,
            'created' => new Sql\Expression('NOW()'),
        ]);

        return $token;
    }
}
