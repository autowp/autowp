<?php

namespace Autowp\User\Model;

use DateInterval;
use DateTime;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;

use Autowp\Commons\Db\Table\Row;

class User
{
    const MIN_NAME = 2;
    const MAX_NAME = 50;
    const MIN_PASSWORD = 6;
    const MAX_PASSWORD = 50;

    /**
     * @var TableGateway
     */
    private $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function updateSpecsVolumes()
    {
        $select = $this->table->getSql()->select()
            ->columns(['id', 'count' => Sql\Expression('count(attrs_user_values.user_id)')])
            ->join('attrs_user_values', 'attrs_user_values.user_id = users.id', [], $select::JOIN_LEFT)
            ->where('not users.specs_volume_valid')
            ->group('users.id');

        foreach ($this->table->selectWith($select) as $row) {
            $this->table->update([
                'specs_volume'       => $row['count'],
                'specs_volume_valid' => 1
            ], [
                'id = ?' => $row['id']
            ]);
        }
    }

    public function invalidateSpecsVolume(int $userId)
    {
        $this->table->update([
            'specs_volume_valid' => 0
        ], [
            'id = ?' => $userId
        ]);
    }

    private function getMessagingInterval($row)
    {
        $date = Row::getDateTimeByColumnType('timestamp', $row['reg_date']);

        $defaultInterval = 300;

        if (! $date) {
            return $defaultInterval;
        }

        $tenDaysBefore = (new DateTime())->sub(new DateInterval('P10D'));
        if ($tenDaysBefore > $date) {
            return $row['messaging_interval'];
        }

        return max($row['messaging_interval'], $defaultInterval);
    }

    public function getNextMessageTime(int $userId)
    {
        $row = $this->table->select(['id' => $userId])->current();
        if (! $row) {
            return false;
        }

        $lastMessageTime = Row::getDateTimeByColumnType('timestamp', $row['last_message_time']);

        if ($lastMessageTime) {
            $messagingInterval = $this->getMessagingInterval($row);
            if ($messagingInterval) {
                $interval = new DateInterval('PT'.$messagingInterval.'S');
                return $lastMessageTime->add($interval);
            }
        }

        return false;
    }

    public function getTable(): TableGateway
    {
        return $this->table;
    }

    private function applyIdFilter(Sql\Select $select, $value, string $id)
    {
        if (is_array($value)) {
            $value = array_values($value);

            if (count($value) == 1) {
                $this->applyIdFilter($select, $value[0], $id);
                return;
            }

            if (count($value) < 1) {
                $this->applyIdFilter($select, 0, $id);
                return;
            }

            $select->where([new Sql\Predicate\In($id, $value)]);
            return;
        }

        if (! is_scalar($value)) {
            throw new \Exception('`id` must be scalar or array of scalar');
        }

        $select->where([$id => $value]);
    }

    private function getSelect($options): Sql\Select
    {
        if (! is_array($options)) {
            $options = ['id' => $options];
        }

        $defaults = [
            'id'          => null,
            'not_deleted' => null,
            'search'      => null,
        ];
        $options = array_replace($defaults, $options);

        $select = $this->table->getSql()->select();

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], 'users.id');
        }

        if ($options['not_deleted']) {
            $select->where(['not users.deleted']);
        }

        if ($options['search']) {
            $select->where(['users.name like ?' => $options['search'] . '%']);
        }

        return $select;
    }

    public function getRow($options)
    {
        $select = $this->getSelect($options);

        return $this->table->selectWith($select)->current();
    }

    public function getRows($options): array
    {
        $select = $this->getSelect($options);

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = $row;
        }

        return $result;
    }
}
