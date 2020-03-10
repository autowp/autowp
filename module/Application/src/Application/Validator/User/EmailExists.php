<?php

namespace Application\Validator\User;

use Autowp\User\Model\User;
use Exception;
use Laminas\Validator\AbstractValidator;

class EmailExists extends AbstractValidator
{
    private const NOT_EXISTS = 'userEmailNotExists';

    protected array $messageTemplates = [
        self::NOT_EXISTS => "E-mail '%value%' not registered",
    ];

    private User $userModel;

    public function setUserModel(User $userModel)
    {
        $this->userModel = $userModel;

        return $this;
    }

    /**
     * @param mixed $value
     * @throws Exception
     */
    public function isValid($value): bool
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
