<?php

namespace Application\Validator\User;

use Autowp\User\Model\User;
use Exception;
use Laminas\Validator\AbstractValidator;

class EmailNotExists extends AbstractValidator
{
    private const EXISTS = 'userEmailExists';

    protected array $messageTemplates = [
        self::EXISTS => "E-mail '%value%' already registered",
    ];

    private User $userModel;

    public function setUserModel(User $userModel): self
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

        if ($exists) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
