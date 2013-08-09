<?php

class Project_Validate_User_Login implements Zend_Validate_Interface
{
    const USER_NOT_FOUND = 1;

    protected $_errors = array(
        self::USER_NOT_FOUND => 'Пользователь с именем или e-mail "%s" не найден'
    );

    protected $_messages = array();

    public function isValid($value)
    {
        $this->_messages = array();

        $users = new Users();
        $user = $users->fetchRow(
            $users->select()
                  ->where('login = ?', $value)
                  ->orWhere('e_mail = ?', $value)
        );

        if (!$user) {
            $this->_messages[] = sprintf($this->_errors[self::USER_NOT_FOUND], $value);
            return false;
        }

        return true;
    }

    public function getMessages()
    {
        return $this->_messages;
    }

    public function getErrors()
    {
        return array_keys($this->_messages);
    }
}