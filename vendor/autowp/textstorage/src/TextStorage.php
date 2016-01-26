<?php

namespace Autowp;

class TextStorage
{

    /**
     * \Zend_Db_Adapter_Abstract object.
     *
     * @var Zend_Db_Adapter_Abstract
     */
    private $_db = null;

    /**
     * @var \Zend_Db_Table
     */
    private $_textTable = null;

    /**
     * @var \Zend_Db_Table
     */
    private $_revisionTable = null;

    /**
     * @var string
     */
    private $_textTableName = 'textstorage_text';

    /**
     * @var string
     */
    private $_revisionTableName = 'textstorage_revision';

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->setOptions($options);
    }

    /**
     * @param array $options
     * @return TextStorage
     * @throws TextStorage\Exception
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            $method = 'set' . ucfirst($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $this->_raise("Unexpected option '$key'");
            }
        }

        return $this;
    }

    /**
     * @param string $message
     * @throws TextStorage\Exception
     */
    private function _raise($message)
    {
        throw new TextStorage\Exception($message);
    }

    /**
     * @param Zend_Db_Adapter_Abstract $dbAdapter
     * @return TextStorage
     */
    public function setDbAdapter(\Zend_Db_Adapter_Abstract $dbAdapter)
    {
        $this->_db = $dbAdapter;

        return $this;
    }

    /**
     * @param string $name
     * @return TextStorage
     */
    public function setTextTableName($name)
    {
        $this->_textTableName = (string)$name;

        return $this;
    }

    /**
     * @param string $name
     * @return TextStorage
     */
    public function setRevisionTableName($name)
    {
        $this->_revisionTableName = (string)$name;

        return $this;
    }

    /**
     * @return \Zend_Db_Table
     */
    private function _getTextTable()
    {
        if (null === $this->_textTable) {
            $this->_textTable = new \Zend_Db_Table(array(
                \Zend_Db_Table_Abstract::ADAPTER => $this->_db,
                \Zend_Db_Table_Abstract::NAME    => $this->_textTableName,
            ));
        }

        return $this->_textTable;
    }

    /**
     * @return \Zend_Db_Table
     */
    private function _getRevisionTable()
    {
        if (null === $this->_revisionTable) {
            $this->_revisionTable = new \Zend_Db_Table(array(
                \Zend_Db_Table_Abstract::ADAPTER => $this->_db,
                \Zend_Db_Table_Abstract::NAME    => $this->_revisionTableName,
            ));
        }

        return $this->_revisionTable;
    }

    public function getText($id)
    {
        $row = $this->_getTextTable()->fetchRow(array(
            'id = ?' => (int)$id
        ));

        if ($row) {
            return $row->text;
        } else {
            return null;
        }
    }

    public function getTextInfo($id)
    {
        $row = $this->_getTextTable()->fetchRow(array(
            'id = ?' => (int)$id
        ));

        if ($row) {
            return array(
                'text'     => $row->text,
                'revision' => $row->revision
            );
        } else {
            return null;
        }
    }

    public function getRevisionInfo($id, $revision)
    {
        $row = $this->_getRevisionTable()->fetchRow(array(
            'text_id = ?' => (int)$id,
            'revision =?' => (int)$revision
        ));

        if ($row) {
            return array(
                'text'     => $row->text,
                'revision' => $row->revision,
                'user_id'  => $row->user_id
            );
        } else {
            return null;
        }
    }

    public function setText($id, $text, $userId)
    {
        $row = $this->_getTextTable()->fetchRow(array(
            'id = ?' => (int)$id
        ));

        if (!$row) {
            return $this->_raise('Text `' . $id . '` not found');
        }

        if ($row->text != $text) {

            $row->setFromArray(array(
                'revision'     => new \Zend_Db_Expr('revision + 1'),
                'text'         => $text,
                'last_updated' => new \Zend_Db_Expr('NOW()')
            ));
            $row->save();

            $revisionRow = $this->_getRevisionTable()->createRow(array(
                'text_id'   => $row->id,
                'revision'  => $row->revision,
                'text'      => $row->text,
                'timestamp' => $row->last_updated,
                'user_id'   => $userId
            ));
            $revisionRow->save();
        }

        return $row->id;
    }

    public function createText($text, $userId)
    {
        $row = $this->_getTextTable()->createRow(array(
            'revision'     => 0,
            'text'         => '',
            'last_updated' => new \Zend_Db_Expr('NOW()')
        ));
        $row->save();

        return $this->setText($row->id, $text, $userId);
    }

    public function getTextUserIds($id)
    {
        $table = $this->_getRevisionTable();
        $db = $table->getAdapter();
        return $db->fetchCol(
            $db->select()
                ->from($table->info('name'), 'user_id')
                ->where('user_id')
                ->where('text_id = ?', (int)$id)
        );
    }
}