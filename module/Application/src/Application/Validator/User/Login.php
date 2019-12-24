<?php

namespace Application\Validator\User;

use Zend\Db\Sql;
use Zend\Validator\AbstractValidator;
use Autowp\User\Model\User;

class Login extends AbstractValidator
{
    private const USER_NOT_FOUND = 'userNotFound';

    protected $messageTemplates = [
        self::USER_NOT_FOUND => "login/user-%value%-not-found"
    ];

    /**
     * @var User
     */
    private $userModel;

    public function setUserModel(User $userModel)
    {
        $this->userModel = $userModel;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $table = $this->userModel->getTable();

        $user = $table->select([
            new Sql\Predicate\Expression('login = ? or e_mail = ?', [(string)$value, (string)$value])
        ])->current();

        if (! $user) {
            $this->error(self::USER_NOT_FOUND);
            return false;
        }

        return true;
    }
}
