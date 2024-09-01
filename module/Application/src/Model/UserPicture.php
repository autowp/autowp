<?php

namespace Application\Model;

use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;

class UserPicture
{
    private TableGateway $userTable;

    public function __construct(TableGateway $userTable)
    {
        $this->userTable = $userTable;
    }

    public function incrementUploads(int $userId): void
    {
        $this->userTable->update([
            'pictures_added' => new Sql\Expression('pictures_added+1'),
        ], [
            'id' => $userId,
        ]);
    }
}
