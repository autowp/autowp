<?php

namespace Autowp\User\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class UserRemember
{
    /**
     * @var TableGateway
     */
    private $table;

    /**
     * @var string
     */
    private $salt;

    public function __construct(TableGateway $table, string $salt)
    {
        $this->table = $table;
        $this->salt = $salt;
    }

    public function garbageCollect(): int
    {
        return (int)$this->table->delete([
            'date < DATE_SUB(NOW(), INTERVAL 60 DAY)'
        ]);
    }

    /**
     * @suppress PhanUndeclaredMethod, PhanDeprecatedFunction
     * @param int $userId
     * @return string
     */
    public function createToken(int $userId): string
    {
        do {
            $token = md5($this->salt . microtime());
            $row = $this->table->select([
                'token' => $token
            ])->current();
        } while ($row);

        $this->table->insert([
            'user_id' => $userId,
            'token'   => $token,
            'date'    => new Sql\Expression('NOW()')
        ]);

        return $token;
    }

    /**
     * @suppress PhanUndeclaredMethod
     * @param int $userId
     * @return string
     */
    public function getUserToken(int $userId): string
    {
        $select = new Sql\Select($this->table->getTable());
        $select->columns(['token'])
            ->where(['user_id' => $userId])
            ->limit(1);

        $row = $this->table->selectWith($select)->current();

        return $row ? $row['token'] : '';
    }
}
