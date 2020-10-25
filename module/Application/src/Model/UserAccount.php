<?php

namespace Application\Model;

use Laminas\Db\TableGateway\TableGateway;

use function str_replace;

class UserAccount
{
    private TableGateway $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function getServiceExternalId(int $userId, string $service): string
    {
        $row = $this->table->select([
            'user_id'    => $userId,
            'service_id' => $service,
        ])->current();
        if (! $row) {
            return '';
        }

        return $row['external_id'];
    }

    public function getUserId(string $service, string $externalId): int
    {
        $row = $this->table->select([
            'external_id' => $externalId,
            'service_id'  => $service,
        ])->current();
        if (! $row) {
            return 0;
        }

        return $row['user_id'];
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

    public function haveAccountsForOtherServices(int $userId, int $id): bool
    {
        return (bool) $this->table->select([
            'user_id' => $userId,
            'id != ?' => $id,
        ])->current();
    }

    public function removeAccount(int $id): bool
    {
        $affected = $this->table->delete([
            'id' => $id,
        ]);

        return $affected > 0;
    }

    public function removeUserAccounts(int $userId): void
    {
        $this->table->delete([
            'user_id = ?' => $userId,
        ]);
    }

    public function setAccountData(string $service, string $externalId, array $data): void
    {
        $this->table->update([
            'name' => $data['name'],
            'link' => (string) $data['link'],
        ], [
            'service_id'  => $service,
            'external_id' => $externalId,
        ]);
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
