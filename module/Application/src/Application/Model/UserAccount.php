<?php

namespace Application\Model;

use Zend\Db\TableGateway\TableGateway;

class UserAccount
{
    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function getServiceExternalId(int $userId, string $service): string
    {
        $row = $this->table->select([
            'user_id'    => $userId,
            'service_id' => $service
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
            'service_id'  => $service
        ])->current();
        if (! $row) {
            return 0;
        }

        return $row['user_id'];
    }

    public function getAccounts(int $userId): array
    {
        $rows = $this->table->select([
            'user_id' => $userId
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'name'       => $row['name'],
                'link'       => $row['link'],
                'icon'       => 'fa fa-' . str_replace('googleplus', 'google-plus', $row['service_id']),
                'service_id' => $row['service_id']
            ];
        }

        return $result;
    }

    public function haveAccountsForOtherServices(int $userId, strint $service): bool
    {
        return (bool)$this->table->select([
            'user_id'         => $userId,
            'service_id != ?' => $service
        ])->current();
    }

    public function removeAccount(int $userId, string $service)
    {
        $affected = $this->table->delete([
            'user_id = ?'    => $userId,
            'service_id = ?' => $service
        ]);

        return $affected > 0;
    }

    public function removeUserAccounts(int $userId)
    {
        $this->table->delete([
            'user_id = ?' => $userId
        ]);
    }

    public function setAccountData(string $service, string $externalId, array $data)
    {
        $this->table->update([
            'name' => $data['name'],
            'link' => $data['link'],
        ], [
            'service_id'  => $service,
            'external_id' => $externalId,
        ]);
    }

    public function create(string $service, string $externalId, array $data)
    {
        $this->table->insert([
            'service_id'   => $service,
            'external_id'  => $externalId,
            'user_id'      => $data['user_id'],
            'used_for_reg' => $data['used_for_reg'],
            'name'         => $data['name'],
            'link'         => $data['link']
        ]);
    }
}
