<?php

namespace Application\Validator\Attr;

use Zend\Db\TableGateway\TableGateway;
use Zend\Validator\AbstractValidator;

class AttributeId extends AbstractValidator
{
    private const INVALID = 'invalidAttributeId';

    protected $messageTemplates = [
        self::INVALID => "Attribute is invalid"
    ];

    /**
     * @var TableGateway
     */
    private $table;

    public function setTable(TableGateway $table)
    {
        $this->table = $table;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $row = $this->table->select([
            'id' => (int)$value
        ]);

        if (! $row) {
            $this->error(self::INVALID);
            return false;
        }
        return true;
    }
}
