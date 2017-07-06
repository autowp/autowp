<?php

namespace Application\Model;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

class Contact
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function create(int $userId, int $contactUserId)
    {
        $primaryKey = [
            'user_id'         => $userId,
            'contact_user_id' => $contactUserId
        ];

        $row = $this->table->select($primaryKey)->current();
        if (! $row) {
            $row = $this->table->insert(array_merge([
                'timestamp' => new Sql\Expression('now()')
            ], $primaryKey));
        }
    }

    public function delete(int $userId, int $contactUserId)
    {
        $this->table->delete([
            'user_id'         => $userId,
            'contact_user_id' => $contactUserId
        ]);
    }

    public function exists(int $userId, int $contactUserId)
    {
        $row = $this->table->select([
            'user_id'         => $userId,
            'contact_user_id' => $contactUserId
        ])->current();
        return (bool)$row;
    }

    public function deleteUserEverywhere(int $userId)
    {
        $this->table->delete([
            'user_id' => $userId
        ]);

        $this->table->delete([
            'contact_user_id' => $userId
        ]);
    }
}
