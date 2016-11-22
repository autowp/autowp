<?php

namespace Application\Validator\User;

use Zend\Validator\AbstractValidator;

class EmailExists extends AbstractValidator
{
    const NOT_EXISTS = 'userEmailNotExists';

    protected $messageTemplates = [
        self::NOT_EXISTS => "E-mail '%value%' not registered"
    ];

    public function isValid($value)
    {
        $this->setValue($value);

        $table = new \Autowp\User\Model\DbTable\User();
        $db = $table->getAdapter();

        $exists = $db->fetchOne(
            $db->select()
                ->from($table->info('name'), 'id')
                ->where('e_mail = ?', $value)
        );
        if (! $exists) {
            $this->error(self::NOT_EXISTS);
            return false;
        }
        return true;
    }
}
