<?php

namespace Autowp\User\Model;

use Application\Module;
use ArrayAccess;
use ArrayObject;
use Autowp\Commons\Db\Table\Row;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql;
use Laminas\Db\TableGateway\TableGateway;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Paginator;

use function array_replace;
use function array_values;
use function Autowp\Commons\currentFromResultSetInterface;
use function count;
use function inet_pton;
use function is_array;
use function is_scalar;
use function max;

class User
{
    public const MIN_NAME     = 2;
    public const MAX_NAME     = 50;
    public const MIN_PASSWORD = 6;
    public const MAX_PASSWORD = 50;

    private TableGateway $table;

    public function __construct(TableGateway $table)
    {
        $this->table = $table;
    }

    public function invalidateSpecsVolume(int $userId): void
    {
        $this->table->update([
            'specs_volume_valid' => 0,
        ], [
            'id = ?' => $userId,
        ]);
    }

    /**
     * @param array|ArrayAccess $row
     * @throws Exception
     */
    private function getMessagingInterval($row): int
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

    /**
     * @throws Exception
     */
    public function getNextMessageTime(int $userId): ?DateTime
    {
        $row = currentFromResultSetInterface($this->table->select(['id' => $userId]));
        if (! $row) {
            return null;
        }

        $lastMessageTime = Row::getDateTimeByColumnType('timestamp', $row['last_message_time']);

        if ($lastMessageTime) {
            $messagingInterval = $this->getMessagingInterval($row);
            if ($messagingInterval) {
                $interval = new DateInterval('PT' . $messagingInterval . 'S');
                return $lastMessageTime->add($interval);
            }
        }

        return null;
    }

    public function getTable(): TableGateway
    {
        return $this->table;
    }

    /**
     * @param array|int $value
     * @throws Exception
     */
    private function applyIdFilter(Sql\Select $select, $value, string $id): void
    {
        if (is_array($value)) {
            $value = array_values($value);

            if (count($value) === 1) {
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
            throw new Exception('`id` must be scalar or array of scalar');
        }

        $select->where([$id => $value]);
    }

    /**
     * @param int|array $options
     * @throws Exception
     */
    private function getSelect($options): Sql\Select
    {
        $options = is_array($options) ? $options : ['id' => $options];

        $defaults = [
            'id'               => null,
            'identity'         => null,
            'not_deleted'      => null,
            'search'           => null,
            'identity_is_null' => null,
            'online'           => null,
            'limit'            => null,
            'order'            => null,
            'has_specs'        => null,
            'has_pictures'     => null,
            'in_contacts'      => null,
            'email'            => null,
        ];
        $options  = array_replace($defaults, $options);

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
            $now->setTimezone(new DateTimeZone(Module::MYSQL_TIMEZONE));
            $now->sub(new DateInterval('PT5M'));

            $select->where(['users.last_online >= ?' => $now->format(Module::MYSQL_DATETIME_FORMAT)]);
        }

        if ($options['limit']) {
            $select->limit((int) $options['limit']);
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
                ->where(['contact.user_id' => (int) $options['in_contacts']]);
        }

        if ($options['email']) {
            $select->where(['e_mail = ?' => (string) $options['email']]);
        }

        return $select;
    }

    /**
     * @param int|array $options
     * @return array|ArrayObject|null
     * @throws Exception
     */
    public function getRow($options)
    {
        $select = $this->getSelect($options);

        return currentFromResultSetInterface($this->table->selectWith($select));
    }

    /**
     * @param int|array $options
     * @throws Exception
     */
    public function getRows($options): array
    {
        $select = $this->getSelect($options);

        $result = [];
        foreach ($this->table->selectWith($select) as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function getPaginator(array $options): Paginator\Paginator
    {
        /** @var Adapter $adapter */
        $adapter = $this->table->getAdapter();
        return new Paginator\Paginator(
            new Paginator\Adapter\LaminasDb\DbSelect($this->getSelect($options), $adapter)
        );
    }

    /**
     * @throws Exception
     */
    public function getCount(array $options): int
    {
        return $this->getPaginator($options)->getTotalItemCount();
    }

    /**
     * @throws Exception
     */
    public function isExists(array $options): bool
    {
        $select = $this->getSelect($options);
        $select->reset($select::COLUMNS);
        $select->reset($select::ORDER);
        $select->reset($select::GROUP);
        $select->columns(['id']);
        $select->limit(1);

        return (bool) currentFromResultSetInterface($this->table->selectWith($select));
    }

    /**
     * @throws Exception
     */
    public function registerVisit(int $userId, Request $request): void
    {
        $user = $this->getRow($userId);
        if (! $user) {
            return;
        }

        $set            = [];
        $nowExpiresDate = (new DateTime())->sub(new DateInterval('PT1S'));
        $lastOnline     = Row::getDateTimeByColumnType('timestamp', $user['last_online']);
        if (! $lastOnline || ($lastOnline < $nowExpiresDate)) {
            $set['last_online'] = new Sql\Expression('NOW()');
        }

        $remoteAddr = $request->getServer('REMOTE_ADDR');
        if ($remoteAddr) {
            $ip = inet_pton($remoteAddr);
            if ($ip !== $user['last_ip']) {
                $set['last_ip'] = $ip;
            }
        }

        if ($set) {
            $this->table->update($set, ['id' => $userId]);
        }
    }

    /**
     * @throws Exception
     */
    public function getUserLanguage(int $userId): string
    {
        $select = $this->table->getSql()->select()
            ->columns(['language'])
            ->where(['id' => $userId]);

        $user = currentFromResultSetInterface($this->table->selectWith($select));

        if (! $user) {
            return '';
        }

        return (string) $user['language'];
    }

    /**
     * @throws Exception
     */
    public function getUserRole(int $userId): string
    {
        $select = $this->table->getSql()->select()
            ->columns(['role'])
            ->where(['id' => $userId]);

        $user = currentFromResultSetInterface($this->table->selectWith($select));

        if (! $user) {
            return '';
        }

        return (string) $user['role'];
    }

    public function decVotes(int $userId): void
    {
        $this->table->update([
            'votes_left' => new Sql\Expression('votes_left - 1'),
        ], [
            'id' => $userId,
        ]);
    }
}
