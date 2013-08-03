<?php

class Project_Validate_User_EmailExists extends Zend_Validate_Abstract
{
    const EXISTS = 'userEmailExists';

    public function isValid($value, $context = null)
    {
        $this->_messages = array();

        $table = new Users();
        $db = $table->getAdapter();

        $exists = $db->fetchOne(
            $table->select()
                ->from($table, array('id'))
                ->where('e_mail = ?', $value)
        );
        if (!$exists) {
            $this->_messages[self::EXISTS] = sprintf("E-mail '%s' не зарегистрирован на сайте", $value);
            return false;
        }
        return true;
    }
}