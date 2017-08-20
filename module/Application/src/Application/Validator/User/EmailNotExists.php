<?php

namespace Application\Validator\User;

use Zend\Validator\AbstractValidator;

use Autowp\User\Model\User;

class EmailNotExists extends AbstractValidator
{
    const EXISTS = 'userEmailExists';

    protected $messageTemplates = [
        self::EXISTS => "E-mail '%value%' already registered"
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

        $exists = $this->userModel->isExists([
            'email' => (string)$value
        ]);

        if ($exists) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
