<?php

namespace Application\Model;

use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

use function array_merge;
use function Autowp\Commons\currentFromResultSetInterface;

class Contact
{
    private TableGateway $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    /**
     * @suppress PhanDeprecatedFunction
     */
    public function create(int $userId, int $contactUserId): void
    {
        $primaryKey = [
            'user_id'         => $userId,
            'contact_user_id' => $contactUserId,
        ];

        $row = currentFromResultSetInterface($this->table->select($primaryKey));
        if (! $row) {
            $this->table->insert(array_merge([
                'timestamp' => new Sql\Expression('now()'),
            ], $primaryKey));
        }
    }

    public function delete(int $userId, int $contactUserId): void
    {
        $this->table->delete([
            'user_id'         => $userId,
            'contact_user_id' => $contactUserId,
        ]);
    }

    public function exists(int $userId, int $contactUserId): bool
    {
        $row = currentFromResultSetInterface($this->table->select([
            'user_id'         => $userId,
            'contact_user_id' => $contactUserId,
        ]));
        return (bool) $row;
    }

    public function deleteUserEverywhere(int $userId): void
    {
        $this->table->delete([
            'user_id' => $userId,
        ]);

        $this->table->delete([
            'contact_user_id' => $userId,
        ]);
    }
}
