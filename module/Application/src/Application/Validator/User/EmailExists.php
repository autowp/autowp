<?php

namespace Application\Validator\User;

use Zend\Validator\AbstractValidator;

use Users;

class EmailExists extends AbstractValidator
{
    const NOT_EXISTS = 'userEmailNotExists';

    protected $messageTemplates = [
        self::NOT_EXISTS => "E-mail '%value%' не зарегистрирован на сайте"
    ];

    public function isValid($value)
    {
        $this->setValue($value);

        $table = new Users();
        $db = $table->getAdapter();

        $exists = $db->fetchOne(
            $db->select()
                ->from($table->info('name'), 'id')
                ->where('e_mail = ?', $value)
        );
        if (!$exists) {
            $this->error(self::NOT_EXISTS);
            return false;
        }
        return true;
    }
}