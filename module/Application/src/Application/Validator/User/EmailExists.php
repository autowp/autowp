<?php

namespace Application\Validator\User;

use Zend\Validator\AbstractValidator;

use Users;

class EmailExists extends AbstractValidator
{
    const EXISTS = 'userEmailExists';

    public function isValid($value, $context = null)
    {
        $this->_messages = [];

        $table = new Users();
        $db = $table->getAdapter();

        $exists = $db->fetchOne(
            $table->select()
                ->from($table, 'id')
                ->where('e_mail = ?', $value)
        );
        if (!$exists) {
            $this->_messages[self::EXISTS] = sprintf("E-mail '%s' не зарегистрирован на сайте", $value);
            return false;
        }
        return true;
    }
}