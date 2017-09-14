<?php

namespace Autowp\User\Model;

use DateInterval;
use DateTime;
use DateTimeZone;

use Zend\Db\Sql;
use Zend\Db\TableGateway\TableGateway;
use Zend\Http\PhpEnvironment\Request;
use Zend\Paginator;

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
            ->columns(['id', 'count' => new Sql\Expression('count(attrs_user_values.user_id)')])
            ->join('attrs_user_values', 'attrs_user_values.user_id = users.id', [], Sql\Select::JOIN_LEFT)
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
            'identity'    => null,
            'not_deleted' => null,
            'search'      => null,
            'identity_is_null' => null,
            'online'           => null,
            'limit'            => null,
            'order'            => null,
            'has_specs'        => null,
            'has_pictures'     => null,
            'in_contacts'      => null,
            'email'            => null,
        ];
        $options = array_replace($defaults, $options);

        $select = $this->table->getSql()->select();

        if ($options['id'] !== null) {
            $this->applyIdFilter($select, $options['id'], 'users.id');
        }

        if ($options['identity'] !== null) {
            $select->where(['users.identity' => $options['identity']]);
        }

        if ($options['not_deleted']) {
            $select->where(['not users.deleted']);
        }

        if ($options['identity_is_null']) {
            $select->where(['users.identity is null']);
        }

        if ($options['search']) {
            $select->where(['users.name like ?' => $options['search'] . '%']);
        }

        if ($options['online']) {
            $now = new DateTime();
            $now->setTimezone(new DateTimeZone(MYSQL_TIMEZONE));
            $now->sub(new DateInterval('PT5M'));

            $select->where(['users.last_online >= ?' => $now->format(MYSQL_DATETIME_FORMAT)]);
        }

        if ($options['limit']) {
            $select->limit($options['limit']);
        }

        if ($options['order']) {
            $select->order($options['order']);
        }

        if ($options['has_specs']) {
            $select->where(['users.specs_volume > 0']);
        }

        if ($options['has_pictures']) {
            $select->where(['users.pictures_total > 0']);
        }

        if ($options['in_contacts']) {
            $select->join('contact', 'users.id = contact.contact_user_id', [])
                ->where(['contact.user_id' => (int)$options['in_contacts']]);
        }

        if ($options['email']) {
            $select->where(['e_mail = ?' => (string)$options['email']]);
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

    public function getPaginator(array $options): Paginator\Paginator
    {
        return new Paginator\Paginator(
            new Paginator\Adapter\DbSelect(
                $this->getSelect($options),
                $this->table->getAdapter()
            )
        );
    }

    public function getCount(array $options): int
    {
        return $this->getPaginator($options)->getTotalItemCount();
    }

    public function isExists(array $options): bool
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->reset($select::ORDER);
        $select->reset($select::GROUP);
        $select->columns(['id']);
        $select->limit(1);

        return (bool)$this->table->selectWith($select)->current();
    }

    public function registerVisit(int $userId, Request $request)
    {
        $user = $this->getRow($userId);
        if (! $user) {
            return;
        }

        $set = [];
        $nowExpiresDate = (new DateTime())->sub(new DateInterval('PT1S'));
        $lastOnline = Row::getDateTimeByColumnType('timestamp', $user['last_online']);
        if (! $lastOnline || ($lastOnline < $nowExpiresDate)) {
            $set['last_online'] = new Sql\Expression('NOW()');
        }

        $remoteAddr = $request->getServer('REMOTE_ADDR');
        if ($remoteAddr) {
            $ip = inet_pton($remoteAddr);
            if ($ip != $user['last_ip']) {
                $set['last_ip'] = $ip;
            }
        }

        if ($set) {
            $this->table->update($set, ['id' => $userId]);
        }
    }

    public function getUserLanguage(int $userId): string
    {
        $select = $this->table->getSql()->select()
            ->columns(['language'])
            ->where(['id' => $userId]);

        $user = $this->table->selectWith($select)->current();

        if (! $user) {
            return '';
        }

        return (string)$user['language'];
    }

    public function getUserRole(int $userId): string
    {
        $select = $this->table->getSql()->select()
            ->columns(['role'])
            ->where(['id' => $userId]);

        $user = $this->table->selectWith($select)->current();

        if (! $user) {
            return '';
        }

        return (string)$user['role'];
    }

    public function decVotes(int $userId)
    {
        $this->table->update([
            'votes_left' => new Sql\Expression('votes_left - 1')
        ], [
            'id' => $userId
        ]);
    }
}
