<?php

namespace Application\Session\SaveHandler;

use Zend_Auth;
use Zend_Config;
use Zend_Db_Expr;
use Zend_Session_SaveHandler_DbTable;
use Zend_Session_SaveHandler_Exception;

class DbTable extends Zend_Session_SaveHandler_DbTable
{
    const USERID_COLUMN = 'userIdColumn';

    /**
     * Session table userId column
     *
     * @var string
     */
    protected $_userIdColumn = null;

    /**
     * Constructor
     *
     * $config is an instance of Zend_Config or an array of key/value pairs containing configuration options for
     * Zend_Session_SaveHandler_DbTable and Zend_Db_Table_Abstract. These are the configuration options for
     * Zend_Session_SaveHandler_DbTable:
     *
     * primaryAssignment => (string|array) Session table primary key value assignment
     *      (optional; default: 1 => sessionId) You have to assign a value to each primary key of your session table.
     *      The value of this configuration option is either a string if you have only one primary key or an array if
     *      you have multiple primary keys. The array consists of numeric keys starting at 1 and string values. There
     *      are some values which will be replaced by session information:
     *
     *      sessionId       => The id of the current session
     *      sessionName     => The name of the current session
     *      sessionSavePath => The save path of the current session
     *
     *      NOTE: One of your assignments MUST contain 'sessionId' as value!
     *
     * modifiedColumn    => (string) Session table last modification time column
     *
     * lifetimeColumn    => (string) Session table lifetime column
     *
     * dataColumn        => (string) Session table data column
     *
     * lifetime          => (integer) Session lifetime (optional; default: ini_get('session.gc_maxlifetime'))
     *
     * userIdColumn      => (string) Session table userId column
     *
     * overrideLifetime  => (boolean) Whether or not the lifetime of an existing session should be overridden
     *      (optional; default: false)
     *
     * @param  Zend_Config|array $config      User-provided configuration
     * @return void
     * @throws Zend_Session_SaveHandler_Exception
     */
    public function __construct($config)
    {
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        } else if (!is_array($config)) {
            throw new Zend_Session_SaveHandler_Exception(
                '$config must be an instance of Zend_Config or array of key/value pairs containing '
              . 'configuration options for Application\\Session\\SaveHandler\\DbTable and Zend_Db_Table_Abstract.');
        }

        foreach ($config as $key => $value) {
            switch ($key) {
                case self::USERID_COLUMN:
                    $this->_userIdColumn = (string)$value;
                    unset($config[$key]);
                    break;
                default:
                    // unrecognized options passed to parent::__construct()
                    break;
            }
        }

        parent::__construct($config);
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $userId = Zend_Auth::getInstance()->getIdentity();
        } else {
            $userId = null;
        }
        return $userId;
    }

    /**
     * Write session data
     *
     * @param string $id
     * @param string $data
     * @return boolean
     */
    public function write($id, $data)
    {
        $return = false;

        $db = $this->getAdapter();
        $userIdColumn = $db->quoteIdentifier($this->_userIdColumn);
        $modifiedColumn = $db->quoteIdentifier($this->_modifiedColumn);
        $dataColumn = $db->quoteIdentifier($this->_dataColumn);
        $lifetimeColumn = $db->quoteIdentifier($this->_lifetimeColumn);

        if ($this->_overrideLifetime) {
            $lifetime = $this->_lifetime;
        } else {
            $lifetime = new Zend_Db_Expr($lifetimeColumn);
        }

        $colNames = [$userIdColumn, $modifiedColumn, $dataColumn, $lifetimeColumn];
        $colValues = ['?', '?', '?', '?'];
        $args = [$this->getUserId(), time(), (string)$data, $this->_lifetime];
        $updateExprs = [
            $userIdColumn . ' = values('.$userIdColumn.')',
            $modifiedColumn . ' = values('.$modifiedColumn.')',
            $dataColumn . ' = values('.$dataColumn.')',
            $lifetimeColumn . ' = '.$db->quote($lifetime)
        ];

        $primary = $this->_getPrimary($id, self::PRIMARY_TYPE_ASSOC);
        foreach ($primary as $column => $value) {
            $colNames[] = $db->quoteIdentifier($column);
            $colValues[] = '?';
            $args[] = $value;
        }

        $sql = 'insert into ' . $db->quoteIdentifier($this->info('name')) .
                   ' ('.implode(', ', $colNames) . ') ' .
               'values (' . implode(', ', $colValues) . ') ' .
               'on duplicate key update ' . implode(', ', $updateExprs);

        $stmt = $db->query($sql, $args);

        return $stmt->rowCount() > 0;
    }
}