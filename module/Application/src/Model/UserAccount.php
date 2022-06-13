<?php

namespace Application\Model;

use Exception;
use Laminas\Db\TableGateway\TableGateway;

use function Autowp\Commons\currentFromResultSetInterface;
use function str_replace;

class UserAccount
{
    private TableGateway $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function getAccounts(int $userId): array
    {
        $rows = $this->table->select([
            'user_id' => $userId,
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id'         => (int) $row['id'],
                'name'       => $row['name'],
                'link'       => $row['link'],
                'icon'       => 'fa fa-' . str_replace('googleplus', 'google-plus', $row['service_id']),
                'service_id' => $row['service_id'],
            ];
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function haveAccountsForOtherServices(int $userId, int $id): bool
    {
        return (bool) currentFromResultSetInterface($this->table->select([
            'user_id' => $userId,
            'id != ?' => $id,
        ]));
    }

    public function removeAccount(int $id): bool
    {
        $affected = $this->table->delete([
            'id' => $id,
        ]);

        return $affected > 0;
    }

    public function create(string $service, string $externalId, array $data): void
    {
        $this->table->insert([
            'service_id'   => $service,
            'external_id'  => $externalId,
            'user_id'      => $data['user_id'],
            'used_for_reg' => $data['used_for_reg'],
            'name'         => $data['name'],
            'link'         => (string) $data['link'],
        ]);
    }
}
