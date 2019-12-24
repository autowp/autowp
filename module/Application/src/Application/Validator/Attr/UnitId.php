<?php

namespace Application\Validator\Attr;

use Zend\Db\TableGateway\TableGateway;
use Zend\Validator\AbstractValidator;

class UnitId extends AbstractValidator
{
    private const INVALID = 'invalidUnitId';

    protected $messageTemplates = [
        self::INVALID => "Unit is invalid"
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
