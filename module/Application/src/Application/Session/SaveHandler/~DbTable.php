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

    /**
     * Write session data
     *
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write($id, $data)
    {
        $data = [
            'modified'   => time(),
            'data'       => (string) $data,
            'account_id' => $this->getUserId(),
        ];

        $row = $this->table->fetchRow([
            'id = ?' => $id
        ]);
            
        if ($row) {
            return (bool) $this->table->update($data, [
                'id = ?' => $id
            ]);
        }
        $data['lifetime'] = $this->lifetime;
        $data['id']       = $id;

        return (bool) $this->table->insert($data);
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
