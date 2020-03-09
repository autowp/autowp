<?php

namespace Application\Validator\Attr;

use Laminas\Db\TableGateway\TableGateway;
use Laminas\Validator\AbstractValidator;

class UnitId extends AbstractValidator
{
    private const INVALID = 'invalidUnitId';

    protected array $messageTemplates = [
        self::INVALID => "Unit is invalid",
    ];

    private TableGateway $table;

    public function setTable(TableGateway $table)
    {
        $this->table = $table;

        return $this;
    }

    public function isValid($value)
    {
        $this->setValue($value);

        $row = $this->table->select([
            'id' => (int) $value,
        ]);

        if (! $row) {
            $this->error(self::INVALID);
            return false;
        }
        return true;
    }
}
