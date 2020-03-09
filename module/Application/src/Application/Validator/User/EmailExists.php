<?php

namespace Application\Validator\User;

use Autowp\User\Model\User;
use Laminas\Validator\AbstractValidator;

class EmailExists extends AbstractValidator
{
    private const NOT_EXISTS = 'userEmailNotExists';

    protected $messageTemplates = [
        self::NOT_EXISTS => "E-mail '%value%' not registered",
    ];

    /** @var User */
    private $userModel;

    public function setUserModel(User $userModel)
    {
        $this->userModel = $userModel;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $exists = $this->userModel->isExists([
            'email' => (string) $value,
        ]);

        if (! $exists) {
            $this->error(self::NOT_EXISTS);
            return false;
        }
        return true;
    }
}
