<?php

namespace Application\Validator\User;

use Autowp\User\Model\User;
use Laminas\Db\Sql;
use Laminas\Validator\AbstractValidator;

class Login extends AbstractValidator
{
    private const USER_NOT_FOUND = 'userNotFound';

    protected array $messageTemplates = [
        self::USER_NOT_FOUND => "login/user-%value%-not-found",
    ];

    private User $userModel;

    public function setUserModel(User $userModel): self
    {
        $this->userModel = $userModel;

        return $this;
    }

    /**
     * @param mixed $value
     */
    public function isValid($value): bool
    {
        $this->setValue($value);

        $table = $this->userModel->getTable();

        $user = $table->select([
            new Sql\Predicate\Expression('login = ? or e_mail = ?', [(string) $value, (string) $value]),
        ])->current();

        if (! $user) {
            $this->error(self::USER_NOT_FOUND);
            return false;
        }

        return true;
    }
}
