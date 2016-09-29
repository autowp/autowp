<?php

namespace Application\Validator\User;

use Zend\Validator\AbstractValidator;

use Users;

class EmailNotExists extends AbstractValidator
{
    const EXISTS = 'userEmailExists';

    protected $messageTemplates = [
        self::EXISTS => "E-mail '%value%' already registered"
    ];

    public function isValid($value)
    {
        $this->setValue($value);

        $table = new Users();
        $db = $table->getAdapter();

        $exists = $db->fetchOne(
            $db->select()
                ->from($table->info('name'), 'id')
                ->where('e_mail = ?', (string)$value)
        );

        if ($exists) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}