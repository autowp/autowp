<?php

namespace Application\Validator\Brand;

use Zend\Validator\AbstractValidator;

use Brands;

class NameNotExists extends AbstractValidator
{
    const EXISTS = 'brandNameAlreadyExists';

    protected $messageTemplates = [
        self::EXISTS => "Бренд с названием '%value%' уже существует"
    ];

    public function isValid($value)
    {
        $this->setValue($value);

        $brands = new Brands();
        $row = $brands->fetchRowByCaption($value);
        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}