<?php

namespace Application\Session\SaveHandler;

use Zend\Authentication\AuthenticationService;
use Zend\Session\SaveHandler\SaveHandlerInterface;

use Zend_Db_Table;

class DbTable implements SaveHandlerInterface
{
    /**
     * Session Save Path
     *
     * @var string
     */
    protected $sessionSavePath;

    /**
     * Session Name
     *
     * @var string
     */
    protected $sessionName;

    /**
     * Lifetime
     * @var int
     */
    protected $lifetime;

    /**
     * @var Zend_Db_Table
     */
    protected $table;

    /**
     * DbTableGateway Options
     * @var DbTableGatewayOptions
     */
    protected $options;

    /**
     * Constructor
     *
     * @param TableGateway $tableGateway
     * @param DbTableGatewayOptions $options
     */
    public function __construct(array $options)
    {
        $this->table = new \Zend_Db_Table($options['table']);
    }

    /**
     * Open Session
     *
     * @param  string $savePath
     * @param  string $name
     * @return bool
     */
    public function open($savePath, $name)
    {
        $this->sessionSavePath = $savePath;
        $this->sessionName     = $name;
        $this->lifetime        = ini_get('session.gc_maxlifetime');

        return true;
    }

    /**
     * Close session
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data
     *
     * @param string $id
     * @param bool $destroyExpired Optional; true by default
     * @return string
     */
    public function read($id, $destroyExpired = true)
    {
        $row = $this->table->fetchRow([
            'id = ?' => $id,
        ]);

        if ($row) {
            if ($row['modified'] + $row['lifetime'] > time()) {
                return (string) $row['data'];
            }
            if ($destroyExpired) {
                $this->destroy($id);
            }
        }
        return '';
    }

    public function write($id, $data)
    {
        $db = $this->table->getAdapter();

        $sql =  'insert into ' . $db->quoteIdentifier($this->table->info('name')) .
                    ' (id, user_id, modified, data) ' .
                'values (?, ?, ?, ?) ' .
                'on duplicate key update '.
                    'user_id = values(user_id), ' .
                    'modified = values(modified), ' .
                    'data = values(data)';

        $stmt = $db->query($sql, [
            $id,
            $this->getUserId(),
            time(),
            (string)$data
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Destroy session
     *
     * @param  string $id
     * @return bool
     */
    public function destroy($id)
    {
        if (! (bool) $this->read($id, false)) {
            return true;
        }

        return (bool) $this->table->delete([
            'id = ?' => $id,
        ]);
    }

    /**
     * Garbage Collection
     *
     * @param int $maxlifetime
     * @return true
     */
    public function gc($maxlifetime)
    {
        return (bool) $this->table->delete([
            'modified < ?' => time() - $this->lifetime
        ]);
    }

    public function getUserId()
    {
        $auth = new AuthenticationService();
        if ($auth->hasIdentity()) {
            $userId = $auth->getIdentity();
        } else {
            $userId = null;
        }
        return $userId;
    }
}
