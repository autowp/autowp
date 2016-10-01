<?php

namespace Application\Validator\Brand;

use Zend\Validator\AbstractValidator;

use Application\Model\DbTable\Brand as BrandTable;

class NameNotExists extends AbstractValidator
{
    const EXISTS = 'brandNameAlreadyExists';

    protected $messageTemplates = [
        self::EXISTS => "Brand '%value%' already exists"
    ];

    public function isValid($value)
    {
        $this->setValue($value);

        $brandTable = new BrandTable();
        $row = $brandTable->fetchRow([
            'caption = ?' => (string)$value
        ]);
        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}