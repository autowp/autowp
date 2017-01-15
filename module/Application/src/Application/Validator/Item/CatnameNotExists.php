<?php

namespace Application\Validator\Item;

use Zend\Validator\AbstractValidator;

use Application\Model\DbTable;

class CatnameNotExists extends AbstractValidator
{
    const EXISTS = 'itemCatnameAlreadyExists';

    protected $messageTemplates = [
        self::EXISTS => "Catname '%value%' already exists"
    ];

    private $exclude;

    public function setExclude($exclude)
    {
        $this->exclude = (int)$exclude;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $table = new DbTable\Item();
        $row = $table->fetchRow([
            'catname = ?' => (string)$value,
            'id <> ?'     => (int)$this->exclude
        ]);
        if ($row) {
            $this->error(self::EXISTS);
            return false;
        }
        return true;
    }
}
