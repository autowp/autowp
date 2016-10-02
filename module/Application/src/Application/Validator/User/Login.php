<?php

namespace Application\Validator\User;

use Zend\Validator\AbstractValidator;

class Login extends AbstractValidator
{
    const USER_NOT_FOUND = 'userNotFound';

    protected $messageTemplates = [
        self::USER_NOT_FOUND => "login/user-%value%-not-found"
    ];

    public function isValid($value)
    {
        $this->setValue($value);

        $users = new \Application\Model\DbTable\User();
        $user = $users->fetchRow(
            $users->select(true)
                  ->where('login = ?', (string)$value)
                  ->orWhere('e_mail = ?', (string)$value)
        );

        if (!$user) {
            $this->error(self::USER_NOT_FOUND);
            return false;
        }

        return true;
    }
}